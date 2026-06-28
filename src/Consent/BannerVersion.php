<?php

declare(strict_types=1);

namespace LRob\CookieConsent\Consent;

use LRob\CookieConsent\Frontend\Banner;
use LRob\CookieConsent\Support\Categories;
use LRob\CookieConsent\Support\Rules;

/**
 * Versions the whole cookie-consent context shown to the visitor: the banner
 * text, the category labels/descriptions, AND what is actually blocked under
 * each category. Each distinct snapshot gets a stable hash recorded once;
 * consent records store the hash in force when they consented. Editing the
 * text or the block rules yields a new hash — old records keep the old one.
 */
final class BannerVersion
{
    /**
     * Server-side snapshot — used only as a fallback. Reflects the site's base
     * language (what __() returns), active categories only.
     *
     * @return array{texts:array<string,string>,categories:array<string,array{title:string,desc:string}>,blocking:array<string,list<array{pattern:string,service:string}>>}
     */
    public static function snapshot(): array
    {
        $t = Banner::texts();
        $labels = Categories::labels();
        $categories = [];
        foreach (array_merge(['functional'], Rules::active_categories()) as $slug) {
            if (isset($labels[$slug])) {
                $categories[$slug] = ['title' => self::clean($labels[$slug]['title']), 'desc' => self::clean($labels[$slug]['desc'])];
            }
        }

        return [
            'texts' => [
                'header'  => self::clean($t['header']),
                'message' => self::clean($t['message']),
                'accept'  => self::clean($t['accept']),
                'deny'    => self::clean($t['deny']),
                'save'    => self::clean($t['save']),
            ],
            'categories' => $categories,
            'blocking'   => self::blocking(),
        ];
    }

    /**
     * Record the banner exactly as the visitor saw it — text captured from the
     * rendered DOM (post-translation, active categories only) plus the server's
     * blocking map. This is what proves what was actually shown, in the visitor's
     * language. Returns the version hash.
     *
     * @param array<string,mixed> $shown
     */
    public static function record(array $shown): string
    {
        $clean = static fn ($s): string => self::clean(sanitize_text_field((string) $s));

        $categories = [];
        $raw = is_array($shown['categories'] ?? null) ? $shown['categories'] : [];
        foreach ($raw as $slug => $c) {
            $slug = sanitize_key((string) $slug);
            if ($slug === '' || !is_array($c)) {
                continue;
            }
            $categories[$slug] = ['title' => $clean($c['title'] ?? ''), 'desc' => $clean($c['desc'] ?? '')];
        }

        return self::store([
            'texts' => [
                'header'  => $clean($shown['header'] ?? ''),
                'message' => $clean($shown['message'] ?? ''),
                'accept'  => $clean($shown['accept'] ?? ''),
                'deny'    => $clean($shown['deny'] ?? ''),
                'save'    => $clean($shown['save'] ?? ''),
            ],
            'categories' => $categories,
            'blocking'   => self::blocking(),
        ]);
    }

    /** What each active category blocks (config — language-independent). */
    private static function blocking(): array
    {
        $compiled = Rules::compiled();
        $blocking = [];
        foreach ($compiled['rules'] as $r) {
            $blocking[$r['category']][] = ['pattern' => $r['pattern'], 'service' => $r['service']];
        }
        foreach ($compiled['inline'] as $i) {
            $blocking[$i['category']][] = ['pattern' => '(inline script)', 'service' => $i['name']];
        }
        return $blocking;
    }

    /**
     * Strip translation-plugin gettext markers (e.g. TranslatePress'
     * #!trpst#…#!trpen# wrappers) so the snapshot stores clean text and its hash
     * stays stable across the markers' changing internal IDs.
     */
    public static function clean(string $text): string
    {
        return trim((string) preg_replace('/#!trpst#.*?#!trpen#/s', '', $text));
    }

    /** Insert a snapshot if its hash is new; return the hash. */
    private static function store(array $snapshot): string
    {
        global $wpdb;
        $json = (string) wp_json_encode($snapshot);
        $hash = substr(md5($json), 0, 40);
        $table = Schema::versions_table();
        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE version_hash = %s", $hash));
        if (!$exists) {
            $wpdb->insert(
                $table,
                ['version_hash' => $hash, 'snapshot' => $json, 'created_at' => gmdate('Y-m-d H:i:s')],
                ['%s', '%s', '%s']
            );
        }
        return $hash;
    }

    /** Server-side fallback: record the base-language snapshot. */
    public static function ensure(): string
    {
        return self::store(self::snapshot());
    }

    /** @return array<string,mixed>|null */
    public static function get(string $hash): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare('SELECT * FROM ' . Schema::versions_table() . ' WHERE version_hash = %s', $hash),
            ARRAY_A
        );
        if (!is_array($row)) {
            return null;
        }
        $row['snapshot'] = json_decode((string) $row['snapshot'], true);
        return $row;
    }

    /** @return list<array<string,mixed>> each with decoded snapshot */
    public static function all(): array
    {
        global $wpdb;
        $rows = $wpdb->get_results('SELECT version_hash, snapshot, created_at FROM ' . Schema::versions_table() . ' ORDER BY id DESC', ARRAY_A);
        if (!is_array($rows)) {
            return [];
        }
        foreach ($rows as &$row) {
            $row['snapshot'] = json_decode((string) $row['snapshot'], true);
        }
        return $rows;
    }

    /** @return array<string,array<string,mixed>> hash → decoded snapshot, for lookups/export */
    public static function map(): array
    {
        $out = [];
        foreach (self::all() as $row) {
            $out[(string) $row['version_hash']] = is_array($row['snapshot']) ? $row['snapshot'] : [];
        }
        return $out;
    }

    /** Drop version snapshots no longer referenced by any consent record. */
    public static function prune_orphans(): void
    {
        global $wpdb;
        $versions = Schema::versions_table();
        $log = Schema::table_name();
        $wpdb->query("DELETE FROM {$versions} WHERE version_hash NOT IN (SELECT DISTINCT banner_version FROM {$log})");
    }
}
