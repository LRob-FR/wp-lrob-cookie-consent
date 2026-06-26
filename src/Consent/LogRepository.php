<?php

declare(strict_types=1);

namespace LRob\CookieConsent\Consent;

use LRob\CookieConsent\Support\Options;

final class LogRepository
{
    public const PURGE_HOOK = 'lrob_cc_purge_log';

    public function register(): void
    {
        add_action(self::PURGE_HOOK, [$this, 'purge_expired']);
        if (!wp_next_scheduled(self::PURGE_HOOK)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::PURGE_HOOK);
        }
    }

    /**
     * @param array{consent_id:string,user_id:int,event_type:string,method:string,choices:string,payload:string,banner_version:string,config_version:string,ip_anon:string,user_agent:string} $row
     */
    public function insert(array $row): void
    {
        global $wpdb;
        $now = time();
        $days = max(1, (int) Options::get('cookie_days'));
        $wpdb->insert(
            Schema::table_name(),
            [
                'created_at'     => gmdate('Y-m-d H:i:s', $now),
                'expires_at'     => gmdate('Y-m-d H:i:s', $now + $days * DAY_IN_SECONDS),
                'consent_id'     => $row['consent_id'],
                'user_id'        => $row['user_id'],
                'event_type'     => $row['event_type'],
                'method'         => $row['method'],
                'choices'        => $row['choices'],
                'payload'        => $row['payload'],
                'banner_version' => $row['banner_version'],
                'config_version' => $row['config_version'],
                'ip_anon'        => $row['ip_anon'],
                'user_agent'     => $row['user_agent'],
            ],
            ['%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );
    }

    public function count(): int
    {
        global $wpdb;
        return (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . Schema::table_name());
    }

    /** @return array{accept_all:int,deny_all:int,custom:int} */
    public function decision_counts(): array
    {
        global $wpdb;
        $rows = $wpdb->get_results('SELECT method, COUNT(*) AS n FROM ' . Schema::table_name() . ' GROUP BY method', ARRAY_A);
        $out = ['accept_all' => 0, 'deny_all' => 0, 'custom' => 0];
        foreach ((array) $rows as $r) {
            $m = (string) ($r['method'] ?? '');
            $bucket = $m === 'accept_all' ? 'accept_all' : ($m === 'deny_all' ? 'deny_all' : 'custom');
            $out[$bucket] += (int) $r['n'];
        }
        return $out;
    }

    /**
     * @return array{rows:list<array<string,mixed>>,total:int}
     */
    public function query(int $per_page, int $offset, string $orderby = 'id', string $order = 'DESC'): array
    {
        global $wpdb;
        $orderby = in_array($orderby, ['id', 'created_at', 'event_type', 'method'], true) ? $orderby : 'id';
        $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';
        $table = Schema::table_name();
        $rows = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$table} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d", $per_page, $offset),
            ARRAY_A
        );
        return ['rows' => is_array($rows) ? $rows : [], 'total' => $this->count()];
    }

    public function delete(int $id): void
    {
        global $wpdb;
        $wpdb->delete(Schema::table_name(), ['id' => $id], ['%d']);
        BannerVersion::prune_orphans();
    }

    /** @param list<int> $ids */
    public function delete_ids(array $ids): void
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if ($ids === []) {
            return;
        }
        global $wpdb;
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $wpdb->query($wpdb->prepare('DELETE FROM ' . Schema::table_name() . " WHERE id IN ({$placeholders})", $ids));
        BannerVersion::prune_orphans();
    }

    public function purge_all(): void
    {
        global $wpdb;
        $wpdb->query('TRUNCATE TABLE ' . Schema::table_name());
        BannerVersion::prune_orphans();
    }

    public function purge_expired(): void
    {
        $days = (int) Options::get('log_retention_days');
        if ($days <= 0) {
            return;
        }
        global $wpdb;
        $cutoff = gmdate('Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS);
        $wpdb->query(
            $wpdb->prepare('DELETE FROM ' . Schema::table_name() . ' WHERE created_at < %s', $cutoff)
        );
        BannerVersion::prune_orphans();
    }

    /** Streams a CSV of the whole log to the browser and exits. */
    public function stream_csv(): void
    {
        global $wpdb;
        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=lrob-cc-consent-log.csv');

        $versions = BannerVersion::map();

        $out = fopen('php://output', 'w');
        // Each row carries the exact information text shown and what every
        // category covered, so a proof line is self-contained for audit.
        fputcsv($out, ['id', 'created_at_utc', 'expires_at_utc', 'visitor_id', 'user_id', 'username', 'event', 'method', 'choices', 'cookie_consent_version', 'info_header', 'info_message', 'categories_shown', 'blocked_by_category', 'config_version', 'ip', 'user_agent']);

        $batch = 1000;
        $offset = 0;
        do {
            $rows = $wpdb->get_results(
                $wpdb->prepare('SELECT * FROM ' . Schema::table_name() . ' ORDER BY id ASC LIMIT %d OFFSET %d', $batch, $offset),
                ARRAY_A
            );
            foreach ((array) $rows as $r) {
                $user = (int) ($r['user_id'] ?? 0);
                $username = $user > 0 ? (string) (get_userdata($user)->user_login ?? '') : '';
                $snap = $versions[(string) ($r['banner_version'] ?? '')] ?? [];
                $texts = is_array($snap['texts'] ?? null) ? $snap['texts'] : [];
                $cats = is_array($snap['categories'] ?? null) ? $snap['categories'] : [];
                $blocking = is_array($snap['blocking'] ?? null) ? $snap['blocking'] : [];
                fputcsv($out, [
                    $r['id'], $r['created_at'], $r['expires_at'], $r['consent_id'], $user, $username,
                    $r['event_type'], $r['method'], $r['choices'], $r['banner_version'],
                    (string) ($texts['header'] ?? ''), (string) ($texts['message'] ?? ''),
                    (string) wp_json_encode($cats), (string) wp_json_encode($blocking),
                    $r['config_version'], $r['ip_anon'], $r['user_agent'],
                ]);
            }
            $offset += $batch;
        } while (is_array($rows) && count($rows) === $batch);

        fclose($out);
        exit;
    }
}
