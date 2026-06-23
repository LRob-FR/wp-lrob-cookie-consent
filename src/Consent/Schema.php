<?php

declare(strict_types=1);

namespace LRob\CookieConsent\Consent;

final class Schema
{
    public const DB_VERSION = 1;
    public const DB_VERSION_OPTION = 'lrob_cc_db_version';

    public static function table_name(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'lrob_cc_consent_log';
    }

    /** Idempotent — safe to call on every activation. */
    public static function create(): void
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table = self::table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            created_at datetime NOT NULL,
            ip_anon varchar(45) NOT NULL DEFAULT '',
            categories varchar(191) NOT NULL DEFAULT '',
            config_version varchar(32) NOT NULL DEFAULT '',
            user_agent varchar(255) NOT NULL DEFAULT '',
            PRIMARY KEY  (id),
            KEY created_at (created_at)
        ) {$charset_collate};";

        dbDelta($sql);

        update_option(self::DB_VERSION_OPTION, self::DB_VERSION);
    }
}
