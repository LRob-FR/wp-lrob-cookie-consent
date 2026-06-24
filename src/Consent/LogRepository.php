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
     * @param array{user_id:int,decision:string,ip_anon:string,categories:string,config_version:string,user_agent:string} $row
     */
    public function insert(array $row): void
    {
        global $wpdb;
        $wpdb->insert(
            Schema::table_name(),
            [
                'created_at'     => gmdate('Y-m-d H:i:s'),
                'user_id'        => $row['user_id'],
                'decision'       => $row['decision'],
                'ip_anon'        => $row['ip_anon'],
                'categories'     => $row['categories'],
                'config_version' => $row['config_version'],
                'user_agent'     => $row['user_agent'],
            ],
            ['%s', '%d', '%s', '%s', '%s', '%s', '%s']
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
        $rows = $wpdb->get_results('SELECT decision, COUNT(*) AS n FROM ' . Schema::table_name() . ' GROUP BY decision', ARRAY_A);
        $out = ['accept_all' => 0, 'deny_all' => 0, 'custom' => 0];
        foreach ((array) $rows as $r) {
            $d = (string) ($r['decision'] ?? '');
            if (isset($out[$d])) {
                $out[$d] = (int) $r['n'];
            }
        }
        return $out;
    }

    /** @return list<array<string,mixed>> */
    public function recent(int $limit = 100, int $offset = 0): array
    {
        global $wpdb;
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * FROM ' . Schema::table_name() . ' ORDER BY id DESC LIMIT %d OFFSET %d',
                $limit,
                $offset
            ),
            ARRAY_A
        );
        return is_array($rows) ? $rows : [];
    }

    public function purge_all(): void
    {
        global $wpdb;
        $wpdb->query('TRUNCATE TABLE ' . Schema::table_name());
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
    }

    /** Streams a CSV of the whole log to the browser and exits. */
    public function stream_csv(): void
    {
        global $wpdb;
        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=lrob-cc-consent-log.csv');

        $out = fopen('php://output', 'w');
        fputcsv($out, ['id', 'created_at_utc', 'user_id', 'username', 'decision', 'ip', 'categories', 'config_version', 'user_agent']);

        $batch = 1000;
        $offset = 0;
        do {
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    'SELECT * FROM ' . Schema::table_name() . ' ORDER BY id ASC LIMIT %d OFFSET %d',
                    $batch,
                    $offset
                ),
                ARRAY_A
            );
            foreach ((array) $rows as $r) {
                $user = (int) ($r['user_id'] ?? 0);
                $username = $user > 0 ? (string) (get_userdata($user)->user_login ?? '') : '';
                fputcsv($out, [
                    $r['id'], $r['created_at'], $user, $username, $r['decision'] ?? '', $r['ip_anon'],
                    $r['categories'], $r['config_version'], $r['user_agent'],
                ]);
            }
            $offset += $batch;
        } while (is_array($rows) && count($rows) === $batch);

        fclose($out);
        exit;
    }
}
