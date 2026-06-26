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
    public static function run(string $provider_id = 'local'): array
    {
        $providers = self::providers();
        $provider = $providers[$provider_id] ?? $providers['local'] ?? new LocalScanner();

        $urls = self::targets();
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
     * The exact set of URLs the "visit pages" scan fetches: the home page, then
     * all published pages and recent posts (most-recently-modified first, capped
     * at 50). The full list is shown in the UI so the scan is predictable.
     *
     * @return list<string>
     */
    public static function targets(): array
    {
        $urls = [home_url('/')];
        $posts = get_posts([
            'numberposts' => 49,
            'post_type'   => ['page', 'post'],
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
        return array_values(array_slice(array_unique($urls), 0, 50));
    }
}
