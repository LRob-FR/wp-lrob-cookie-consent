<?php

declare(strict_types=1);

namespace LRob\CookieConsent\Consent;

final class Schema
{
    public const DB_VERSION = 5;
    public const DB_VERSION_OPTION = 'lrob_cc_db_version';

    public static function table_name(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'lrob_cc_consent_log';
    }

    public static function versions_table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'lrob_cc_banner_versions';
    }

    /** Idempotent — safe to call on every activation. dbDelta adds new columns. */
    public static function create(): void
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table = self::table_name();
        $versions = self::versions_table();
        $charset_collate = $wpdb->get_charset_collate();

        // One row per consent event. choices = granular per-purpose decision
        // (JSON {category:1|0}); payload = raw client act; banner_version links
        // to the exact information text shown then; expires_at = renewal due.
        $log = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            created_at datetime NOT NULL,
            expires_at datetime NOT NULL,
            consent_id varchar(40) NOT NULL DEFAULT '',
            user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            event_type varchar(16) NOT NULL DEFAULT 'consent',
            method varchar(24) NOT NULL DEFAULT '',
            choices text NOT NULL,
            payload text NOT NULL,
            banner_version varchar(40) NOT NULL DEFAULT '',
            config_version varchar(32) NOT NULL DEFAULT '',
            ip varchar(64) NOT NULL DEFAULT '',
            user_agent varchar(255) NOT NULL DEFAULT '',
            PRIMARY KEY  (id),
            KEY created_at (created_at),
            KEY consent_id (consent_id)
        ) {$charset_collate};";

        $ver = "CREATE TABLE {$versions} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            version_hash varchar(40) NOT NULL,
            snapshot longtext NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY version_hash (version_hash)
        ) {$charset_collate};";

        dbDelta($log);
        dbDelta($ver);

        update_option(self::DB_VERSION_OPTION, self::DB_VERSION);
    }

    /** Run the idempotent migration when the stored schema is behind. */
    public static function maybe_upgrade(): void
    {
        if ((int) get_option(self::DB_VERSION_OPTION, 0) >= self::DB_VERSION) {
            return;
        }
        self::migrate();
        self::create();
    }

    /** Column changes dbDelta can't make on its own. */
    private static function migrate(): void
    {
        global $wpdb;
        $table = self::table_name();
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
            return; // fresh install — create() builds it correctly
        }
        // v5: ip_anon → ip (an IP isn't necessarily anonymous).
        $cols = $wpdb->get_col("DESC {$table}", 0);
        if (is_array($cols) && in_array('ip_anon', $cols, true) && !in_array('ip', $cols, true)) {
            $wpdb->query("ALTER TABLE {$table} CHANGE ip_anon ip varchar(64) NOT NULL DEFAULT ''");
        }
    }
}
