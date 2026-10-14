<?php

declare(strict_types=1);

namespace LearnUponLMS\Models;

final class SeatAllocationRepository
{
    /**
     * Return paginated order seat tracker rows.
     */
    public function getRows(
        int $limit,
        int $offset,
        string $search = ''
    ): array {
        global $wpdb;

        $trackers = $wpdb->prefix . 'lms_order_seat_trackers';
        $users = $wpdb->users;
        $posts = $wpdb->posts;

        [$where, $params] = $this->buildSearch($search);

        $sql = "
            SELECT
                t.id,
                t.order_id,
                t.order_item_id,
                t.head_manager_user_id,
                t.product_id,
                t.course_id,
                t.purchased_seats,
                t.allocated_seats,
                t.status,
                t.updated_at,
                u.display_name AS head_manager_name,
                u.user_email AS head_manager_email,
                p.post_title AS product_name
            FROM {$trackers} AS t
            LEFT JOIN {$users} AS u
                ON u.ID = t.head_manager_user_id
            LEFT JOIN {$posts} AS p
                ON p.ID = t.product_id
            {$where}
            ORDER BY t.id DESC
            LIMIT %d OFFSET %d
        ";

        $params[] = max(1, $limit);
        $params[] = max(0, $offset);

        return $wpdb->get_results(
            $wpdb->prepare($sql, $params),
            ARRAY_A
        ) ?: [];
    }

    /**
     * Count matching tracker rows.
     */
    public function countRows(string $search = ''): int
    {
        global $wpdb;

        $trackers = $wpdb->prefix . 'lms_order_seat_trackers';
        $users = $wpdb->users;
        $posts = $wpdb->posts;

        [$where, $params] = $this->buildSearch($search);

        $sql = "
            SELECT COUNT(*)
            FROM {$trackers} AS t
            LEFT JOIN {$users} AS u
                ON u.ID = t.head_manager_user_id
            LEFT JOIN {$posts} AS p
                ON p.ID = t.product_id
            {$where}
        ";

        if ($params !== []) {
            $sql = $wpdb->prepare($sql, $params);
        }

        return (int) $wpdb->get_var($sql);
    }

    /**
     * Build safe search conditions.
     *
     * @return array{0: string, 1: array}
     */
    private function buildSearch(string $search): array
    {
        global $wpdb;

        if ($search === '') {
            return ['', []];
        }

        $like = '%' . $wpdb->esc_like($search) . '%';

        return [
            'WHERE (
                CAST(t.order_id AS CHAR) LIKE %s
                OR CAST(t.order_item_id AS CHAR) LIKE %s
                OR CAST(t.head_manager_user_id AS CHAR) LIKE %s
                OR u.display_name LIKE %s
                OR u.user_email LIKE %s
                OR p.post_title LIKE %s
                OR t.course_id LIKE %s
                OR t.status LIKE %s
            )',
            [
                $like,
                $like,
                $like,
                $like,
                $like,
                $like,
                $like,
                $like,
            ],
        ];
    }
}
