<?php

declare(strict_types=1);

namespace LRob\CookieConsent\Scanning;

use LRob\CookieConsent\Support\Services;

/**
 * Fetches public pages as an anonymous visitor and extracts cross-origin
 * <script>/<iframe>/<img> sources plus first-party Set-Cookie names. Anonymous
 * by design: no auth cookie is sent, so admin/member-only cookies never appear.
 * Server-side only — cannot see resources injected later by JavaScript.
 */
final class LocalScanner implements ScanProvider
{
    public function id(): string
    {
        return 'local';
    }

    public function label(): string
    {
        return __('Local crawl', 'lrob-cookie-consent');
    }

    /**
     * @param list<string> $urls
     * @return array{resources: list<array<string,mixed>>, cookies: list<string>, error: string}
     */
    public function scan(array $urls): array
    {
        $resources = [];
        $cookies = [];
        $error = '';
        foreach ($urls as $url) {
            $one = $this->scan_url($url);
            if ($one['error'] !== '' && $error === '') {
                $error = $one['error'];
            }
            foreach ($one['resources'] as $r) {
                $resources[$r['pattern']] = $r;
            }
            foreach ($one['cookies'] as $c) {
                if (!in_array($c, $cookies, true)) {
                    $cookies[] = $c;
                }
            }
        }
        return ['resources' => array_values($resources), 'cookies' => $cookies, 'error' => $error];
    }

    /**
     * Scan a single URL. error = '' on success, 'ssl' on a certificate failure,
     * or the raw transport error message otherwise.
     *
     * @return array{resources: list<array<string,mixed>>, cookies: list<string>, error: string}
     */
    public function scan_url(string $url, bool $insecure = false): array
    {
        $response = wp_remote_get($url, [
            'timeout'     => 8,
            'redirection' => 3,
            'sslverify'   => !$insecure,
            'cookies'     => [],
            'user-agent'  => 'LRobCookieConsent-Scan/1.0 (+' . home_url() . ')',
        ]);
        if (is_wp_error($response)) {
            $msg = $response->get_error_message();
            $is_ssl = stripos($msg, 'ssl') !== false || stripos($msg, 'certificate') !== false;
            return ['resources' => [], 'cookies' => [], 'error' => $is_ssl ? 'ssl' : $msg];
        }

        $site_host = (string) wp_parse_url(home_url(), PHP_URL_HOST);
        $services = Services::common();
        $resources = [];
        $cookies = [];

        $set_cookie = wp_remote_retrieve_header($response, 'set-cookie');
        foreach ((is_array($set_cookie) ? $set_cookie : [$set_cookie]) as $cookie_header) {
            if (!is_string($cookie_header) || $cookie_header === '') {
                continue;
            }
            $parts = explode('=', $cookie_header, 2);
            $cookie_name = trim($parts[0]);
            if ($cookie_name !== '' && !in_array($cookie_name, $cookies, true)) {
                $cookies[] = $cookie_name;
            }
        }

        $body = wp_remote_retrieve_body($response);
        if ($body !== '' && preg_match_all('/<(script|iframe|img)\b[^>]*\b(?:src)\s*=\s*["\']([^"\']+)["\']/i', $body, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $type = strtolower($m[1]);
                $src = html_entity_decode($m[2]);
                $host = (string) wp_parse_url($src, PHP_URL_HOST);
                if ($host === '' || $host === $site_host) {
                    continue; // first-party / relative — not a third-party tracker
                }
                $key = $this->match_service($src, $services);
                $pattern = $key['pattern'] ?? $host;
                if (isset($resources[$pattern])) {
                    continue;
                }
                $resources[$pattern] = [
                    'pattern'  => $pattern,
                    'host'     => $host,
                    'type'     => $type,
                    'category' => $key['category'] ?? '',
                    'service'  => $key['service'] ?? $host,
                    'known'    => $key !== null,
                    'sample'   => $src,
                ];
            }
        }

        return ['resources' => array_values($resources), 'cookies' => $cookies, 'error' => ''];
    }

    /**
     * Database scan: read the post_content of all published posts and pages and
     * find external embeds/scripts. Predictable (covers every published item, no
     * HTTP) but only sees what's in the content — not theme/plugin-injected
     * scripts or auto-embeds that render only at request time.
     *
     * @return array{resources: list<array<string,mixed>>, cookies: list<string>, error: string, scanned: int}
     */
    public function scan_content(): array
    {
        $site_host = (string) wp_parse_url(home_url(), PHP_URL_HOST);
        $services = Services::common();
        $resources = [];

        $ids = get_posts([
            'post_type'   => ['post', 'page'],
            'post_status' => 'publish',
            'numberposts' => 1000,
            'fields'      => 'ids',
            'orderby'     => 'modified',
            'order'       => 'DESC',
        ]);

        foreach ($ids as $pid) {
            $content = (string) get_post_field('post_content', (int) $pid);
            if ($content === '') {
                continue;
            }
            $sample = (string) get_permalink((int) $pid);

            if (preg_match_all('/<(script|iframe|img)\b[^>]*\bsrc\s*=\s*["\']([^"\']+)["\']/i', $content, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $m) {
                    $src = html_entity_decode($m[2]);
                    $host = (string) wp_parse_url($src, PHP_URL_HOST);
                    if ($host === '' || $host === $site_host) {
                        continue;
                    }
                    $key = $this->match_service($src, $services);
                    $pattern = $key['pattern'] ?? $host;
                    if (!isset($resources[$pattern])) {
                        $resources[$pattern] = [
                            'pattern' => $pattern, 'host' => $host, 'type' => strtolower($m[1]),
                            'category' => $key['category'] ?? '', 'service' => $key['service'] ?? $host,
                            'known' => $key !== null, 'sample' => $sample,
                        ];
                    }
                }
            }

            // Provider URLs anywhere in content — catches oEmbed-by-URL (e.g. a
            // bare youtu.be/watch link or a Gutenberg embed block), which never
            // contains the rendered "/embed" iframe src.
            $haystack = strtolower($content);
            foreach (self::detection_map() as $d) {
                if (isset($resources[$d['pattern']])) {
                    continue;
                }
                foreach ($d['needles'] as $needle) {
                    if (str_contains($haystack, $needle)) {
                        $resources[$d['pattern']] = [
                            'pattern' => $d['pattern'], 'host' => '', 'type' => 'embed',
                            'category' => $d['category'], 'service' => $d['service'],
                            'known' => true, 'sample' => $sample,
                        ];
                        break;
                    }
                }
            }
        }

        return ['resources' => array_values($resources), 'cookies' => [], 'error' => '', 'scanned' => count($ids)];
    }

    /**
     * Broad host needles → the precise rule to suggest. Used by the database
     * scan to catch oEmbeds whose content holds only the provider URL.
     *
     * @return list<array{needles:list<string>,pattern:string,category:string,service:string}>
     */
    private static function detection_map(): array
    {
        return apply_filters('lrob_cc_detection_map', [
            ['needles' => ['youtube.com', 'youtu.be'], 'pattern' => 'youtube.com/embed', 'category' => 'embed', 'service' => 'YouTube'],
            ['needles' => ['vimeo.com'], 'pattern' => 'player.vimeo.com', 'category' => 'embed', 'service' => 'Vimeo'],
            ['needles' => ['dailymotion.com', 'dai.ly'], 'pattern' => 'dailymotion.com/embed', 'category' => 'embed', 'service' => 'Dailymotion'],
            ['needles' => ['platform.twitter.com', 'twitter.com', 'x.com'], 'pattern' => 'platform.twitter.com', 'category' => 'embed', 'service' => 'X (Twitter)'],
            ['needles' => ['instagram.com'], 'pattern' => 'instagram.com/embed', 'category' => 'embed', 'service' => 'Instagram'],
            ['needles' => ['tiktok.com'], 'pattern' => 'tiktok.com/embed', 'category' => 'embed', 'service' => 'TikTok'],
            ['needles' => ['google.com/maps', 'maps.google.com'], 'pattern' => 'google.com/maps/embed', 'category' => 'embed', 'service' => 'Google Maps'],
            ['needles' => ['fonts.googleapis.com', 'fonts.gstatic.com'], 'pattern' => 'fonts.googleapis.com', 'category' => 'functional', 'service' => 'Google Fonts'],
        ]);
    }

    /**
     * @param list<array{label:string,pattern:string,category:string,service:string}> $services
     * @return array{pattern:string,category:string,service:string}|null
     */
    private function match_service(string $src, array $services): ?array
    {
        foreach ($services as $svc) {
            if (str_contains($src, $svc['pattern'])) {
                return ['pattern' => $svc['pattern'], 'category' => $svc['category'], 'service' => $svc['service']];
            }
        }
        return null;
    }
}
