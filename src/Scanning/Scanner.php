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
     * @return array{provider:string,urls:list<string>,resources:list<array<string,mixed>>,cookies:list<string>}
     */
    public static function run(string $provider_id = 'local'): array
    {
        $providers = self::providers();
        $provider = $providers[$provider_id] ?? $providers['local'] ?? new LocalScanner();

        $urls = self::sample_urls();
        $result = $provider->scan($urls);

        return [
            'provider'  => $provider->id(),
            'urls'      => $urls,
            'resources' => $result['resources'] ?? [],
            'cookies'   => $result['cookies'] ?? [],
        ];
    }

    /**
     * Home + a few recent published posts/pages. Kept small (≤4 × short timeout)
     * so the whole scan finishes well inside PHP's max_execution_time.
     *
     * @return list<string>
     */
    private static function sample_urls(): array
    {
        $urls = [home_url('/')];
        $posts = get_posts([
            'numberposts' => 3,
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
        return array_values(array_slice(array_unique($urls), 0, 4));
    }
}
