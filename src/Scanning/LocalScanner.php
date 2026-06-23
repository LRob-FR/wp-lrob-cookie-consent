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
     * @return array{resources: list<array<string,mixed>>, cookies: list<string>}
     */
    public function scan(array $urls): array
    {
        $site_host = (string) wp_parse_url(home_url(), PHP_URL_HOST);
        $services = Services::common();
        $resources = [];
        $cookies = [];

        foreach ($urls as $url) {
            $response = wp_remote_get($url, [
                'timeout'     => 8,
                'redirection' => 3,
                'cookies'     => [],
                'user-agent'  => 'LRobCookieConsent-Scan/1.0 (+' . home_url() . ')',
            ]);
            if (is_wp_error($response)) {
                continue;
            }

            foreach ((array) wp_remote_retrieve_header($response, 'set-cookie') as $cookie_header) {
                $cookie_header = is_string($cookie_header) ? $cookie_header : '';
                $cookie_name = trim(strtok($cookie_header, '='));
                if ($cookie_name !== '' && !in_array($cookie_name, $cookies, true)) {
                    $cookies[] = $cookie_name;
                }
            }

            $body = wp_remote_retrieve_body($response);
            if ($body === '') {
                continue;
            }

            if (!preg_match_all('/<(script|iframe|img)\b[^>]*\b(?:src)\s*=\s*["\']([^"\']+)["\']/i', $body, $matches, PREG_SET_ORDER)) {
                continue;
            }

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

        return ['resources' => array_values($resources), 'cookies' => $cookies];
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
