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

        $this->collect(wp_remote_retrieve_body($response), $url, $services, $site_host, $resources);

        return ['resources' => array_values($resources), 'cookies' => $cookies, 'error' => ''];
    }

    /**
     * Database scan: read the post_content of all published posts and pages and
     * find external embeds/scripts. Predictable (covers every published item, no
     * HTTP) but only sees what's in the content — not theme/plugin-injected
     * scripts or auto-embeds that render only at request time.
     *
     * Paginated so huge sites stay responsive: the admin UI loops batches with a
     * progress bar, exactly like the visit-pages scan.
     *
     * @return array{resources: list<array<string,mixed>>, cookies: list<string>, error: string, total: int, processed: int, done: bool}
     */
    public function scan_content(int $offset = 0, int $limit = 200): array
    {
        $site_host = (string) wp_parse_url(home_url(), PHP_URL_HOST);
        $services = Services::common();
        $resources = [];

        $total = (int) (wp_count_posts('post')->publish ?? 0) + (int) (wp_count_posts('page')->publish ?? 0);
        $ids = get_posts([
            'post_type'   => ['post', 'page'],
            'post_status' => 'publish',
            'numberposts' => $limit,
            'offset'      => $offset,
            'fields'      => 'ids',
            'orderby'     => 'ID',
            'order'       => 'ASC',
        ]);

        foreach ($ids as $pid) {
            $content = (string) get_post_field('post_content', (int) $pid);
            if ($content === '') {
                continue;
            }
            $sample = (string) get_permalink((int) $pid);

            $this->collect($content, $sample, $services, $site_host, $resources);

            // oEmbeds leave no <iframe>/<script> in stored content, so collect()
            // can't see them. Detect the embed *structurally* — Gutenberg embed
            // blocks (always an embed) and classic bare-URL auto-embeds (only for
            // known oEmbed providers; a bare URL to an unknown host is just a
            // link) — then identify the host. We never flag a provider merely
            // because its name appears somewhere in the text.
            foreach ($this->embed_block_urls($content) as $url) {
                $this->add_embed($url, $sample, $site_host, $resources, true);
            }
            foreach ($this->bare_line_urls($content) as $url) {
                $this->add_embed($url, $sample, $site_host, $resources, false);
            }
        }

        $processed = $offset + count($ids);
        return [
            'resources' => array_values($resources),
            'cookies'   => [],
            'error'     => '',
            'total'     => $total,
            'processed' => $processed,
            'done'      => count($ids) === 0 || $processed >= $total,
        ];
    }

    /**
     * Known providers, keyed by host (+ optional path hint), used ONLY to
     * identify and sort an already-detected embed URL — never to detect one.
     * Matching is host-accurate (dot-boundary), so `x.com` can't match `box.com`.
     *
     * @return list<array{hosts:list<string>,path?:string,pattern:string,category:string,service:string}>
     */
    private static function detection_map(): array
    {
        return apply_filters('lrob_cc_detection_map', [
            ['hosts' => ['youtube.com', 'youtu.be', 'youtube-nocookie.com'], 'pattern' => 'youtube.com/embed', 'category' => 'embed', 'service' => 'YouTube'],
            ['hosts' => ['vimeo.com'], 'pattern' => 'player.vimeo.com', 'category' => 'embed', 'service' => 'Vimeo'],
            ['hosts' => ['dailymotion.com', 'dai.ly'], 'pattern' => 'dailymotion.com/embed', 'category' => 'embed', 'service' => 'Dailymotion'],
            ['hosts' => ['twitter.com', 'x.com'], 'pattern' => 'platform.twitter.com', 'category' => 'embed', 'service' => 'X (Twitter)'],
            ['hosts' => ['instagram.com'], 'pattern' => 'instagram.com/embed', 'category' => 'embed', 'service' => 'Instagram'],
            ['hosts' => ['tiktok.com'], 'pattern' => 'tiktok.com/embed', 'category' => 'embed', 'service' => 'TikTok'],
            ['hosts' => ['google.com', 'maps.google.com'], 'path' => '/maps', 'pattern' => 'google.com/maps/embed', 'category' => 'embed', 'service' => 'Google Maps'],
            ['hosts' => ['fonts.googleapis.com', 'fonts.gstatic.com'], 'pattern' => 'fonts.googleapis.com', 'category' => 'functional', 'service' => 'Google Fonts'],
        ]);
    }

    /**
     * URLs from Gutenberg embed blocks (`<!-- wp:…embed … "url":"…" … -->`).
     * These are real embeds; their rendered iframe/script isn't in stored content.
     *
     * @return list<string>
     */
    private function embed_block_urls(string $content): array
    {
        $urls = [];
        if (preg_match_all('/<!--\s+wp:[^>]*?embed[^>]*?-->/is', $content, $blocks)) {
            foreach ($blocks[0] as $block) {
                if (preg_match('/"url":"([^"]+)"/', $block, $m)) {
                    $urls[] = str_replace('\\/', '/', html_entity_decode($m[1]));
                }
            }
        }
        return $urls;
    }

    /**
     * URLs sitting alone on a line — WordPress auto-embeds these (classic editor).
     *
     * @return list<string>
     */
    private function bare_line_urls(string $content): array
    {
        if (!preg_match_all('/^\s*(https?:\/\/[^\s<"\']+)\s*$/im', $content, $m)) {
            return [];
        }
        return $m[1];
    }

    /**
     * Record a detected embed URL, identifying its host against the known base.
     * $force adds it even if unknown (an explicit embed block); otherwise only a
     * known oEmbed provider counts (a bare URL to an unknown host is just a link).
     *
     * @param array<string,array<string,mixed>> $resources
     */
    private function add_embed(string $url, string $page, string $site_host, array &$resources, bool $force): void
    {
        $host = strtolower((string) wp_parse_url($url, PHP_URL_HOST));
        if ($host === '' || $host === $site_host) {
            return;
        }
        $known = $this->identify_url($url);
        if ($known === null && !$force) {
            return; // bare URL to an unknown host — not an embed, just a link
        }
        $pattern = $known['pattern'] ?? $host;
        if (isset($resources[$pattern])) {
            $this->add_page($resources[$pattern], $page);
            return;
        }
        $resources[$pattern] = [
            'pattern'  => $pattern,
            'host'     => $host,
            'type'     => 'embed',
            'category' => $known['category'] ?? '',
            'service'  => $known['service'] ?? $host,
            'known'    => $known !== null,
            'pages'    => [$page],
        ];
    }

    /**
     * Match a third-party URL to the known base by host (dot-boundary) plus an
     * optional path hint. Returns the identifier, or null when unrecognised.
     *
     * @return array{pattern:string,category:string,service:string}|null
     */
    private function identify_url(string $url): ?array
    {
        $host = strtolower((string) wp_parse_url($url, PHP_URL_HOST));
        if ($host === '') {
            return null;
        }
        $path = strtolower((string) wp_parse_url($url, PHP_URL_PATH));
        foreach (self::detection_map() as $d) {
            foreach ($d['hosts'] as $needle) {
                if ($host !== $needle && !str_ends_with($host, '.' . $needle)) {
                    continue;
                }
                if (!empty($d['path']) && !str_contains($path, $d['path'])) {
                    continue;
                }
                return ['pattern' => $d['pattern'], 'category' => $d['category'], 'service' => $d['service']];
            }
        }
        return null;
    }

    /**
     * Extract cross-origin <script>/<iframe>/<img>/<link> resources from markup
     * into $resources (keyed by suggested rule pattern). $sample labels where it
     * was seen; empty → use the resource URL itself.
     *
     * @param list<array{label:string,pattern:string,category:string,service:string}> $services
     * @param array<string,array<string,mixed>> $resources
     */
    private function collect(string $markup, string $page, array $services, string $site_host, array &$resources): void
    {
        if ($markup === '' || !preg_match_all('/<(script|iframe|img|link)\b[^>]*?\b(?:src|href)\s*=\s*["\']([^"\']+)["\']/i', $markup, $matches, PREG_SET_ORDER)) {
            return;
        }
        foreach ($matches as $m) {
            $tag = strtolower($m[1]);
            $src = html_entity_decode($m[2]);
            $host = (string) wp_parse_url($src, PHP_URL_HOST);
            if ($host === '' || $host === $site_host) {
                continue; // first-party / relative — not a third-party resource
            }
            if ($tag === 'link' && !preg_match('/font|\.css|css\?|stylesheet/i', $src)) {
                continue; // ignore preconnect/icon/etc. links — only fonts & stylesheets
            }
            $key = $this->match_service($src, $services);
            $pattern = $key === null ? $host : (($key['use_host'] ?? false) ? $host : $key['pattern']);
            if ($pattern === '') {
                $pattern = $host;
            }
            $where = $page !== '' ? $page : $src;
            if (isset($resources[$pattern])) {
                $this->add_page($resources[$pattern], $where);
                continue;
            }
            $resources[$pattern] = [
                'pattern'  => $pattern,
                'host'     => $host,
                'type'     => $this->friendly_type($tag, $src, $key),
                'category' => $key['category'] ?? '',
                'service'  => $key['service'] ?? $host,
                'known'    => $key !== null,
                'pages'    => [$where],
            ];
        }
    }

    /** Track where a resource was seen, capped so a site-wide script stays light. */
    private function add_page(array &$resource, string $page): void
    {
        if ($page !== '' && !in_array($page, $resource['pages'], true) && count($resource['pages']) < 100) {
            $resource['pages'][] = $page;
        }
    }

    /** Human-readable resource kind for the scan results column. */
    private function friendly_type(string $tag, string $src, ?array $key): string
    {
        if (($key['use_host'] ?? false) || ($key['category'] ?? '') === 'statistics') {
            return 'tracker';
        }
        return match ($tag) {
            'iframe' => 'embed',
            'img'    => 'image',
            'link'   => preg_match('/font/i', $src) ? 'font' : 'stylesheet',
            default  => 'script',
        };
    }

    /**
     * @param list<array{label:string,pattern:string,category:string,service:string}> $services
     * @return array{pattern:string,category:string,service:string,use_host:bool}|null
     */
    private function match_service(string $src, array $services): ?array
    {
        foreach ($services as $svc) {
            if (str_contains($src, $svc['pattern'])) {
                return ['pattern' => $svc['pattern'], 'category' => $svc['category'], 'service' => $svc['service'], 'use_host' => false];
            }
        }
        // Self-hosted analytics on a custom domain (e.g. mm01.example.net): identify
        // by path, but suggest blocking the whole host.
        foreach (['matomo.php', 'matomo.js', 'piwik.php', 'piwik.js'] as $needle) {
            if (str_contains($src, $needle)) {
                return ['pattern' => '', 'category' => 'statistics', 'service' => 'Matomo', 'use_host' => true];
            }
        }
        return null;
    }
}
