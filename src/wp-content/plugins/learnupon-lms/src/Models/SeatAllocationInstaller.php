<?php

declare(strict_types=1);

namespace LearnUponLMS\Models;

final class SeatAllocationInstaller
{
    public static function install(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charsetCollate = $wpdb->get_charset_collate();

        $trackers = $wpdb->prefix . 'lms_order_seat_trackers';
        $groups = $wpdb->prefix . 'lms_group_seat_allocations';
        $assignments = $wpdb->prefix . 'lms_seat_assignments';

        dbDelta("CREATE TABLE {$trackers} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            order_id BIGINT UNSIGNED NOT NULL,
            order_item_id BIGINT UNSIGNED NOT NULL,
            head_manager_user_id BIGINT UNSIGNED NOT NULL,
            product_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            course_id VARCHAR(191) DEFAULT NULL,
            purchased_seats INT UNSIGNED NOT NULL DEFAULT 0,
            allocated_seats INT UNSIGNED NOT NULL DEFAULT 0,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY uq_order_item (order_item_id),
            KEY idx_head_manager_status (head_manager_user_id, status),
            KEY idx_order_id (order_id)
        ) {$charsetCollate};");

        dbDelta("CREATE TABLE {$groups} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            tracker_id BIGINT UNSIGNED NOT NULL,
            head_manager_user_id BIGINT UNSIGNED NOT NULL,
            group_manager_user_id BIGINT UNSIGNED NOT NULL,
            allocated_seats INT UNSIGNED NOT NULL DEFAULT 0,
            assigned_seats INT UNSIGNED NOT NULL DEFAULT 0,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY uq_tracker_manager (tracker_id, group_manager_user_id),
            KEY idx_head_manager (head_manager_user_id, status),
            KEY idx_group_manager (group_manager_user_id, status)
        ) {$charsetCollate};");

        dbDelta("CREATE TABLE {$assignments} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            tracker_id BIGINT UNSIGNED NOT NULL,
            group_allocation_id BIGINT UNSIGNED NOT NULL,
            group_member_user_id BIGINT UNSIGNED NOT NULL,
            assigned_by BIGINT UNSIGNED NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY uq_tracker_member (tracker_id, group_member_user_id),
            KEY idx_group_allocation_status (group_allocation_id, status),
            KEY idx_member_status (group_member_user_id, status)
        ) {$charsetCollate};");
    }
}
