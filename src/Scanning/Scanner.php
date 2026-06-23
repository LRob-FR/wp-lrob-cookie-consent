<?php

declare(strict_types=1);

namespace LRob\CookieConsent\Scanning;

/**
 * Orchestrates a site scan: picks the active provider, samples public URLs,
 * runs the scan. Providers are filterable so a remote LRob deep-scan can be
 * registered later without touching this class.
 */
final class Scanner
{
    /** @return array<string,ScanProvider> */
    public static function providers(): array
    {
        $providers = ['local' => new LocalScanner()];
        return apply_filters('lrob_cc_scan_providers', $providers);
    }

    /**
     * @return array{provider:string,urls:list<string>,resources:list<array<string,mixed>>,cookies:list<string>,error:string}
     */
    public static function run(string $provider_id = 'local', string $mode = 'simple'): array
    {
        $providers = self::providers();
        $provider = $providers[$provider_id] ?? $providers['local'] ?? new LocalScanner();

        $urls = self::targets($mode);
        $result = $provider->scan($urls);

        return [
            'provider'  => $provider->id(),
            'urls'      => $urls,
            'resources' => $result['resources'] ?? [],
            'cookies'   => $result['cookies'] ?? [],
            'error'     => $result['error'] ?? '',
        ];
    }

    /**
     * URLs to scan. simple = home + a few recent posts/pages (~4); deep = more
     * pages (capped). The client scans these one at a time with a progress bar.
     *
     * @return list<string>
     */
    public static function targets(string $mode = 'simple'): array
    {
        $deep = ($mode === 'deep');
        $urls = [home_url('/')];
        $posts = get_posts([
            'numberposts' => $deep ? 30 : 3,
            'post_type'   => ['post', 'page'],
            'post_status' => 'publish',
            'orderby'     => 'modified',
            'order'       => 'DESC',
        ]);
        foreach ($posts as $post) {
            $link = get_permalink($post);
            if (is_string($link)) {
                $urls[] = $link;
            }
        }
        return array_values(array_slice(array_unique($urls), 0, $deep ? 30 : 4));
    }
}
