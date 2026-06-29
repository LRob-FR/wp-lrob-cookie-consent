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

        $types = array_map(static fn (array $t): array => ['type' => $t['type'], 'limit' => 0, 'order' => 'newest'], self::scan_types());
        $urls = self::targets_from($types);
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
     * Public post types offered to the granular "visit pages" scan, each with
     * its published count. Pages and posts first, then any other public CPT.
     *
     * @return list<array{type:string,label:string,count:int}>
     */
    public static function scan_types(): array
    {
        $count = static fn (string $type): int => (int) (wp_count_posts($type)->publish ?? 0);
        $public = self::public_post_types();
        $ordered = array_values(array_filter(['page', 'post'], static fn (string $t): bool => in_array($t, $public, true)));
        foreach ($public as $type) {
            if (!in_array($type, ['page', 'post'], true)) {
                $ordered[] = $type;
            }
        }
        $out = [];
        foreach ($ordered as $type) {
            $obj = get_post_type_object($type);
            $label = ($obj && isset($obj->labels->name)) ? (string) $obj->labels->name : $type;
            $out[] = ['type' => $type, 'label' => $label, 'count' => $count($type)];
        }
        return $out;
    }

    /**
     * Build the exact URL list for the "visit pages" scan from a granular
     * per-type selection — each entry {type, limit (0 = all), order
     * (newest|oldest)}. The home page is always first.
     *
     * @param list<array<string,mixed>> $types
     * @return list<string>
     */
    public static function targets_from(array $types): array
    {
        $urls = [home_url('/')];
        $valid = self::public_post_types();
        foreach ($types as $spec) {
            if (!is_array($spec)) {
                continue;
            }
            $type = sanitize_key((string) ($spec['type'] ?? ''));
            if (!in_array($type, $valid, true)) {
                continue;
            }
            $limit = max(0, (int) ($spec['limit'] ?? 0));
            $order = (($spec['order'] ?? 'newest') === 'oldest') ? 'ASC' : 'DESC';
            $ids = get_posts([
                'numberposts' => $limit > 0 ? $limit : -1,
                'post_type'   => $type,
                'post_status' => 'publish',
                'fields'      => 'ids',
                'orderby'     => 'date',
                'order'       => $order,
            ]);
            foreach ($ids as $pid) {
                $link = get_permalink((int) $pid);
                if (is_string($link)) {
                    $urls[] = $link;
                }
            }
        }
        return array_values(array_unique($urls));
    }
}
