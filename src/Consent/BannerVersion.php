<?php

declare(strict_types=1);

namespace LRob\CookieConsent\Consent;

use LRob\CookieConsent\Frontend\Banner;
use LRob\CookieConsent\Support\Categories;

/**
 * Versions the information text shown in the banner. Each distinct snapshot
 * (header/message/buttons/category labels) gets a stable hash recorded once;
 * consent records store the hash of the text actually displayed, and editing
 * the text simply yields a new hash — old records keep pointing to the old one.
 */
final class BannerVersion
{
    /** @return array{texts:array<string,string>,categories:array<string,array{title:string,desc:string}>} */
    public static function snapshot(): array
    {
        $t = Banner::texts();
        return [
            'texts' => [
                'header'  => $t['header'],
                'message' => $t['message'],
                'accept'  => $t['accept'],
                'deny'    => $t['deny'],
                'save'    => $t['save'],
            ],
            'categories' => Categories::labels(),
        ];
    }

    public static function hash(): string
    {
        return substr(md5((string) wp_json_encode(self::snapshot())), 0, 40);
    }

    /** Record the current snapshot if new; return its hash. */
    public static function ensure(): string
    {
        global $wpdb;
        $hash = self::hash();
        $table = Schema::versions_table();
        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE version_hash = %s", $hash));
        if (!$exists) {
            $wpdb->insert(
                $table,
                ['version_hash' => $hash, 'snapshot' => (string) wp_json_encode(self::snapshot()), 'created_at' => gmdate('Y-m-d H:i:s')],
                ['%s', '%s', '%s']
            );
        }
        return $hash;
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
}
