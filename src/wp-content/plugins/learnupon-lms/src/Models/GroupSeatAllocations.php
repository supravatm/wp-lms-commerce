<?php

declare(strict_types=1);

namespace LearnUponLMS\Models;

use LearnUponLMS\Models\AbstractModel;

class GroupSeatAllocations extends AbstractModel
{
    protected string $primaryKey = 'id';

    protected function getTableName(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'lms_group_seat_allocations';
    }

    /**
     * Fetches courses thoes are assigned by a company manager
     *
     * @param integer $userId
     * @return array
     */
    public function getAssignedCourses(int $userId): array
    {
        $sqlQuery = "SELECT
            t.id AS tracker_id,
            t.order_id,
            t.order_item_id,
            t.product_id,
            t.course_id,
            t.purchased_seats,
            t.allocated_seats AS tracker_allocated_seats,
            t.status AS tracker_status,


            ga.id AS allocation_id,
            ga.group_manager_user_id,
            ga.allocated_seats AS group_allocated_seats,
            ga.assigned_seats AS group_assigned_seats,
            ga.status AS allocation_status,

            oi.order_item_name

        FROM {$this->wpdb->prefix}lms_order_seat_trackers AS t

        LEFT JOIN {$this->wpdb->prefix}lms_group_seat_allocations AS ga
            ON ga.tracker_id = t.id
            AND ga.status = 'active'

        LEFT JOIN {$this->wpdb->prefix}woocommerce_order_items AS oi
            ON oi.order_item_id = t.order_item_id
            AND oi.order_id = t.order_id
            AND oi.order_item_type = 'line_item'

        WHERE ga.group_manager_user_id = %d
            AND t.status = 'active';
    ";


        $result = $this->wpdb->get_results(
            $this->wpdb->prepare(
                $sqlQuery,
                $userId
            ),
            OBJECT
        ) ?: [];


        return $result;
    }

    public function getSelfAssinmentCourses(int $userId): array
    {
        $sql = "SELECT
            t.id AS tracker_id,
            t.order_id,
            t.order_item_id,
            t.product_id,
            t.course_id,
            t.purchased_seats,
            t.allocated_seats AS tracker_allocated_seats,
            t.status AS tracker_status,

            sa.id as assignment_id,
            sa.status as assignment_status,
            sa.group_member_user_id,

            oi.order_item_name

        FROM {$this->wpdb->prefix}lms_seat_assignments AS sa

        LEFT JOIN {$this->wpdb->prefix}lms_order_seat_trackers AS t
            ON t.id = sa.tracker_id
            AND t.status = 'active'

        LEFT JOIN {$this->wpdb->prefix}woocommerce_order_items AS oi
            ON oi.order_item_id = t.order_item_id
            AND oi.order_id = t.order_id
            AND oi.order_item_type = 'line_item'

        WHERE sa.group_member_user_id = %d
            AND sa.assigned_by = %d
            AND t.status = 'active';
    ";

        $result = $this->wpdb->get_results(
            $this->wpdb->prepare(
                $sql,
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
