<?php

/**
 * Runs when the plugin is deleted from the WordPress admin.
 *
 * Intentionally conservative: seat tables hold purchase data, so they are only
 * dropped when the site owner opts in via the `learnupon_lms_delete_data_on_uninstall`
 * filter.
 */

defined('WP_UNINSTALL_PLUGIN') || exit;

delete_option('learnupon_lms_settings');
delete_option('learnupon_lms_db_version');

if (apply_filters('learnupon_lms_delete_data_on_uninstall', false)) {
    global $wpdb;

    foreach (['lms_seat_assignments', 'lms_group_seat_allocations', 'lms_order_seat_trackers'] as $table) {
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}{$table}"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    }
}
