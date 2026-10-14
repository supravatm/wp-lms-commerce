<?php
defined('ABSPATH') || exit;

class LUI_Seat_Allocation
{
    private wpdb $db;
    private string $table;

    public function __construct(?wpdb $db = null)
    {
        global $wpdb;
        $this->db = $db ?: $wpdb;
        $this->table = $this->db->prefix . 'lui_seat_allocations';
        add_action('woocommerce_order_status_processing', [$this, 'allocate_from_order']);
        add_action('woocommerce_order_status_completed', [$this, 'allocate_from_order']);
        add_action('woocommerce_order_refunded', [$this, 'handle_refund'], 10, 2);
    }

    public function install(): void
    {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $this->db->get_charset_collate();
        dbDelta("CREATE TABLE {$this->table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            head_manager_user_id bigint(20) unsigned NOT NULL,
            group_manager_user_id bigint(20) unsigned NULL,
            group_member_user_id bigint(20) unsigned NULL,
            order_id bigint(20) unsigned NOT NULL,
            order_item_id varchar(200) NULL,
            purchased_seats int(10) unsigned NOT NULL DEFAULT 0,
            reserved_seats int(10) unsigned NOT NULL DEFAULT 0,
            assigned_seats int(10) unsigned NOT NULL DEFAULT 0,
            status varchar(20) NOT NULL DEFAULT 'active',
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY head_manager_user_id (head_manager_user_id),
            KEY group_manager_course (group_manager_user_id, order_item_id),
            KEY order_id (order_id),
            KEY order_item_id (order_item_id)
        ) $charset;");
    }

    public function allocate_from_order(int $order_id): void
    {
        if (!function_exists('wc_get_order')) {
            return;
        }

        $order = wc_get_order($order_id);

        if (!$order || $order->get_meta('_lui_seats_allocated') === 'yes') {
            return;
        }

        $head_manager_user_id = absint(
            $order->get_meta('_lui_head_manager_user_id')
        );

        if (!$head_manager_user_id) {
            $order->add_order_note(
                'LearnUpon: allocation skipped; Head Manager ID is missing.'
            );
            return;
        }

        $course_seats = [];

        foreach ($order->get_items('line_item') as $item) {
            $product_id = $item->get_variation_id() ?: $item->get_product_id();
            $parent_id = $item->get_product_id();

            $course_id = (string) get_post_meta(
                $product_id,
                '_lui_course_id',
                true
            );

            if ($course_id === '' && $parent_id !== $product_id) {
                $course_id = (string) get_post_meta(
                    $parent_id,
                    '_lui_course_id',
                    true
                );
            }

            $seats_per_item = absint(
                get_post_meta($product_id, '_lui_seat_quantity', true)
            );

            if (!$seats_per_item && $parent_id !== $product_id) {
                $seats_per_item = absint(
                    get_post_meta($parent_id, '_lui_seat_quantity', true)
                );
            }

            if ($course_id === '' || $seats_per_item < 1) {
                continue;
            }

            $quantity = max(0, (int) $item->get_quantity());
            $course_seats[$course_id] =
                ($course_seats[$course_id] ?? 0) + ($seats_per_item * $quantity);
        }

        if (!$course_seats) {
            $order->add_order_note(
                'LearnUpon: no eligible course/seat quantities found. No seats allocated.'
            );
            return;
        }

        $now = current_time('mysql');

        foreach ($course_seats as $course_id => $seats) {
            // Avoid duplicate order/course pool rows on repeated WooCommerce hooks.
            $existing_id = $this->db->get_var(
                $this->db->prepare(
                    "SELECT id FROM {$this->table}
                 WHERE head_manager_user_id = %d
                   AND order_id = %d
                   AND order_item_id = %s
                   AND group_manager_user_id IS NULL
                 LIMIT 1",
                    $head_manager_user_id,
                    $order_id,
                    $course_id
                )
            );

            if ($existing_id) {
                continue;
            }

            $inserted = $this->db->insert(
                $this->table,
                [
                    'head_manager_user_id' => $head_manager_user_id,
                    'group_manager_user_id' => null,
                    'order_id' => $order_id,
                    'order_item_id' => $course_id,
                    'purchased_seats' => $seats,
                    'allocated_seats' => 0,
                    'reserved_seats' => 0,
                    'assigned_seats' => 0,
                    'status' => 'active',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                ['%d', '%d', '%d', '%s', '%d', '%d', '%d', '%d', '%s', '%s', '%s']
            );

            if ($inserted === false) {
                $order->add_order_note(
                    sprintf(
                        'LearnUpon: failed to create seat pool for course %s.',
                        $course_id
                    )
                );
                return;
            }
        }

        // Set this only after all course rows were processed successfully.
        $order->update_meta_data('_lui_seats_allocated', 'yes');
        $order->save();

        $order->add_order_note(
            'LearnUpon: purchased course seat pools created/verified.'
        );
    }

    public function balances(
        int $head_manager_user_id,
        ?string $course_id = null
    ): array {
        $where = "head_manager_user_id = %d AND status = 'active'
              AND group_manager_user_id IS NULL";
        $args = [$head_manager_user_id];

        if ($course_id !== null) {
            $where .= ' AND course_id = %s';
            $args[] = $course_id;
        }

        $row = $this->db->get_row(
            $this->db->prepare(
                "SELECT
                COALESCE(SUM(purchased_seats), 0) AS purchased,
                COALESCE(SUM(reserved_seats), 0) AS reserved,
                COALESCE(SUM(assigned_seats), 0) AS assigned
             FROM {$this->table}
             WHERE {$where}",
                ...$args
            ),
            ARRAY_A
        ) ?: ['purchased' => 0, 'reserved' => 0, 'assigned' => 0];

        $row = array_map('intval', $row);
        $row['available'] = max(
            0,
            $row['purchased'] - $row['reserved'] - $row['assigned']
        );

        return $row;
    }

    /**
     * Atomically reserve seats from a Group Manager's allocation
     * for a specific course.
     */
    public function reserve(
        int $head_manager_user_id,
        int $group_manager_user_id,
        string $course_id,
        int $count = 1
    ): bool|WP_Error {
        $course_id = sanitize_text_field($course_id);
        $count = absint($count);

        if (
            $head_manager_user_id < 1 ||
            $group_manager_user_id < 1 ||
            $course_id === '' ||
            $count < 1
        ) {
            return new WP_Error(
                'lui_invalid_reservation',
                __('Invalid course reservation details.', 'learnupon-integration')
            );
        }

        $this->db->query('START TRANSACTION');

        // Lock the exact allocation row.
        $row = $this->db->get_row(
            $this->db->prepare(
                "SELECT id, allocated_seats, reserved_seats, assigned_seats
             FROM {$this->table}
             WHERE head_manager_user_id = %d
               AND group_manager_user_id = %d
               AND course_id = %s
               AND status = 'active'
             ORDER BY id ASC
             LIMIT 1
             FOR UPDATE",
                $head_manager_user_id,
                $group_manager_user_id,
                $course_id
            )
        );

        if (!$row) {
            $this->db->query('ROLLBACK');

            return new WP_Error(
                'lui_allocation_not_found',
                __('No active seat allocation exists for this Group Manager and course.', 'learnupon-integration')
            );
        }

        $available = (int) $row->allocated_seats
            - (int) $row->reserved_seats
            - (int) $row->assigned_seats;

        if ($available < $count) {
            $this->db->query('ROLLBACK');

            return new WP_Error(
                'lui_insufficient_seats',
                __('Not enough available seats for this course.', 'learnupon-integration')
            );
        }

        $updated = $this->db->query(
            $this->db->prepare(
                "UPDATE {$this->table}
             SET reserved_seats = reserved_seats + %d,
                 updated_at = %s
             WHERE id = %d",
                $count,
                current_time('mysql'),
                (int) $row->id
            )
        );

        if ($updated !== 1) {
            $this->db->query('ROLLBACK');

            return new WP_Error(
                'lui_reservation_failed',
                __('Could not reserve seats.', 'learnupon-integration')
            );
        }

        $this->db->query('COMMIT');

        return true;
    }

    /**
     * Confirm seats after successful LearnUpon enrollment.
     *
     * Uses the same allocation row and moves seats from reserved
     * to assigned.
     */
    public function confirm(
        int $head_manager_user_id,
        int $group_manager_user_id,
        string $course_id,
        int $count = 1
    ): bool {
        $course_id = sanitize_text_field($course_id);
        $count = absint($count);

        if (
            $head_manager_user_id < 1 ||
            $group_manager_user_id < 1 ||
            $course_id === '' ||
            $count < 1
        ) {
            return false;
        }

        $updated = $this->db->query(
            $this->db->prepare(
                "UPDATE {$this->table}
             SET reserved_seats = reserved_seats - %d,
                 assigned_seats = assigned_seats + %d,
                 updated_at = %s
             WHERE head_manager_user_id = %d
               AND group_manager_user_id = %d
               AND course_id = %s
               AND status = 'active'
               AND reserved_seats >= %d
             ORDER BY id ASC
             LIMIT 1",
                $count,
                $count,
                current_time('mysql'),
                $head_manager_user_id,
                $group_manager_user_id,
                $course_id,
                $count
            )
        );

        return $updated === 1;
    }

    /**
     * Release seats after a failed LearnUpon enrollment.
     *
     * Decreases reserved seats on the same allocation row.
     */
    public function release(
        int $head_manager_user_id,
        int $group_manager_user_id,
        string $course_id,
        int $count = 1
    ): bool {
        $course_id = sanitize_text_field($course_id);
        $count = absint($count);

        if (
            $head_manager_user_id < 1 ||
            $group_manager_user_id < 1 ||
            $course_id === '' ||
            $count < 1
        ) {
            return false;
        }

        $updated = $this->db->query(
            $this->db->prepare(
                "UPDATE {$this->table}
             SET reserved_seats = reserved_seats - %d,
                 updated_at = %s
             WHERE head_manager_user_id = %d
               AND group_manager_user_id = %d
               AND course_id = %s
               AND status = 'active'
               AND reserved_seats >= %d
             ORDER BY id ASC
             LIMIT 1",
                $count,
                current_time('mysql'),
                $head_manager_user_id,
                $group_manager_user_id,
                $course_id,
                $count
            )
        );

        return $updated === 1;
    }
}
