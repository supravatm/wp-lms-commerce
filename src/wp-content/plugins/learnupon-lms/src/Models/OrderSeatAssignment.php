<?php

declare(strict_types=1);

namespace LearnUponLMS\Models;

use LearnUponLMS\Models\AbstractModel;

class OrderSeatAssignment extends AbstractModel
{
    protected string $primaryKey = 'id';

    protected function getTableName(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'lms_seat_assignments';
    }

    public function getCourseByGroupMemberId(int $userId): array
    {
        $result = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT
            sa.*,
            t.order_id,
            t.order_item_id,
            t.product_id,
            t.course_id,
            t.purchased_seats,
            t.status,
            oi.order_item_name
        FROM {$this->table} AS sa
        INNER JOIN {$this->wpdb->prefix}lms_order_seat_trackers AS t
            ON t.id = sa.tracker_id
        LEFT JOIN {$this->wpdb->prefix}woocommerce_order_items AS oi
            ON oi.order_item_id = t.order_item_id
            AND oi.order_id = t.order_id
            AND oi.order_item_type = 'line_item'
        WHERE sa.group_member_user_id = %d
          AND sa.status = 'active'
          AND t.status = 'active'",
                $userId,
                $userId
            ),
            OBJECT
        ) ?: [];
        error_log('SQL Query: ' . preg_replace('/\s+/', ' ', trim($this->wpdb->last_query)));
        error_log('Database Error: ' . $this->wpdb->last_error);
        return $result;
    }

    public function getCourseAssignedBySelf(int $userId): array
    {
        $result = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT
            sa.*,
            t.order_id,
            t.order_item_id,
            t.product_id,
            t.course_id,
            t.purchased_seats,
            t.status,
            oi.order_item_name
        FROM {$this->table} AS sa
        INNER JOIN {$this->wpdb->prefix}lms_order_seat_trackers AS t
            ON t.id = sa.tracker_id
        LEFT JOIN {$this->wpdb->prefix}woocommerce_order_items AS oi
            ON oi.order_item_id = t.order_item_id
            AND oi.order_id = t.order_id
            AND oi.order_item_type = 'line_item'
        WHERE sa.group_member_user_id = 0
          AND sa.assigned_by = %d
          AND sa.status = 'active'
          AND t.status = 'active'",
                $userId,
                $userId
            ),
            OBJECT
        ) ?: [];
        error_log('SQL Query: ' . preg_replace('/\s+/', ' ', trim($this->wpdb->last_query)));
        error_log('Database Error: ' . $this->wpdb->last_error);
        return $result;
    }
}
