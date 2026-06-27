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

    /** @return list<string> public post type names (excluding attachments) */
    public static function public_post_types(): array
    {
        $types = get_post_types(['public' => true], 'names');
        unset($types['attachment']);
        return array_values($types);
    }

    /**
     * Selectable scan scopes with their URL counts (capped at the 50 the scan
     * fetches). Always starts with the home page.
     *
     * @return list<array{id:string,label:string,count:int}>
     */
    public static function scopes(): array
    {
        $count = static fn (string $type): int => (int) (wp_count_posts($type)->publish ?? 0);
        $cpt = 0;
        foreach (self::public_post_types() as $type) {
            if (!in_array($type, ['page', 'post'], true)) {
                $cpt += $count($type);
            }
        }
        return [
            ['id' => 'home', 'label' => __('Home page only', 'lrob-cookie-consent'), 'count' => 1],
            ['id' => 'pages', 'label' => __('Home + all pages', 'lrob-cookie-consent'), 'count' => 1 + $count('page')],
            ['id' => 'posts', 'label' => __('Home + all posts', 'lrob-cookie-consent'), 'count' => 1 + $count('post')],
            ['id' => 'all', 'label' => __('Everything (pages, posts, custom types)', 'lrob-cookie-consent'), 'count' => 1 + $count('page') + $count('post') + $cpt],
        ];
    }

    /**
     * The exact URLs the "visit pages" scan fetches for a scope — home page
     * first, then ALL matching published content (newest first). No cap: the
     * admin chose the scope and the UI fetches one page at a time with progress.
     *
     * @return list<string>
     */
    public static function targets(string $scope = 'pages'): array
    {
        $urls = [home_url('/')];
        if ($scope === 'home') {
            return $urls;
        }
        $types = match ($scope) {
            'pages' => ['page'],
            'posts' => ['post'],
            default => self::public_post_types(),
        };
        $posts = get_posts([
            'numberposts' => -1,
            'post_type'   => $types,
            'post_status' => 'publish',
            'fields'      => 'ids',
            'orderby'     => 'modified',
            'order'       => 'DESC',
        ]);
        foreach ($posts as $pid) {
            $link = get_permalink((int) $pid);
            if (is_string($link)) {
                $urls[] = $link;
            }
        }
        return array_values(array_unique($urls));
    }
}
