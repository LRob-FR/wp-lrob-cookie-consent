<?php
/**
 * Full removal. Belt-and-suspenders prefix scan: drops every {prefix}lrob_cc_*
 * table, every lrob_cc_* option/transient, and the capability — so a forgotten
 * key still gets cleaned up.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

// Tables: {prefix}lrob_cc_*
$like = $wpdb->esc_like($wpdb->prefix . 'lrob_cc_') . '%';
$tables = $wpdb->get_col($wpdb->prepare('SHOW TABLES LIKE %s', $like));
foreach ((array) $tables as $table) {
    $wpdb->query('DROP TABLE IF EXISTS `' . str_replace('`', '', $table) . '`');
}

// Options + transients: lrob_cc_*
$option_like = $wpdb->esc_like('lrob_cc_') . '%';
$transient_like = $wpdb->esc_like('_transient_lrob_cc_') . '%';
$transient_timeout_like = $wpdb->esc_like('_transient_timeout_lrob_cc_') . '%';
$option_names = $wpdb->get_col(
    $wpdb->prepare(
        "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
        $option_like,
        $transient_like,
        $transient_timeout_like
    )
);
foreach ((array) $option_names as $option_name) {
    delete_option($option_name);
}

// Scheduled events live in the `cron` option (not lrob_cc_*-named).
wp_clear_scheduled_hook('lrob_cc_purge_log');

// Capability.
$role = get_role('administrator');
if ($role !== null) {
    $role->remove_cap('manage_lrob_cc');
}
