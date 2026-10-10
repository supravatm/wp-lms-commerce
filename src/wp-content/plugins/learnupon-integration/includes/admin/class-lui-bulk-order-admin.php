<?php

defined('ABSPATH') || exit;

final class LUI_Bulk_Order_Admin
{
    public function init(): void
    {
        add_action(
            'add_meta_boxes',
            [$this, 'register_legacy_metabox']
        );

        add_action(
            'add_meta_boxes_woocommerce_page_wc-orders',
            [$this, 'register_hpos_metabox']
        );

        add_action(
            'admin_post_lui_mark_bulk_order',
            [$this, 'mark_bulk_order']
        );
    }

    public function register_legacy_metabox(): void
    {
        add_meta_box(
            'lui_bulk_order',
            'Bulk Order',
            [$this, 'render_metabox'],
            'shop_order',
            'side',
            'high'
        );
    }

    public function register_hpos_metabox(): void
    {
        add_meta_box(
            'lui_bulk_order',
            'Bulk Order',
            [$this, 'render_metabox'],
            'woocommerce_page_wc-orders',
            'side',
            'high'
        );
    }

    public function render_metabox($post_or_order): void
    {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        $order_id = $post_or_order instanceof WC_Order
            ? $post_or_order->get_id()
            : absint($post_or_order->ID ?? 0);

        $order = wc_get_order($order_id);

        if (!$order) {
            echo '<p>Order not found.</p>';
            return;
        }

        if ($order->get_meta('_lui_is_bulk_order') === 'yes') {
            echo '<p><strong>Bulk order approved</strong></p>';
            echo '<p>Customer group: Group manager</p>';
            return;
        }

        $url = wp_nonce_url(
            admin_url(
                'admin-post.php?action=lui_mark_bulk_order&order_id='
                    . $order_id
            ),
            'lui_mark_bulk_order_' . $order_id
        );

        echo '<p>Approve this order as a bulk order and change the customer group.</p>';
        echo '<a class="button button-primary" href="' .
            esc_url($url) .
            '">Mark as Bulk Order</a>';
    }

    public function mark_bulk_order(): void
    {
        $logger = wc_get_logger();
        $source = ['source' => 'lui-bulk-order'];

        $order_id = absint($_GET['order_id'] ?? 0);

        // Redirect back with an error notice and log the error.
        $fail = function (
            string $message,
            int $error_order_id = 0
        ) use ($logger, $source): void {
            $logger->error($message . ' Order ID: ' . $error_order_id, $source);
            $url = admin_url('admin.php?page=wc-orders&action=edit&id=' . $error_order_id);
            $url = add_query_arg('lui_bulk_order_error', rawurlencode($message), $url);
            wp_safe_redirect($url);
            exit;
        };

        if (!current_user_can('manage_woocommerce')) {
            $fail('Permission denied.', $order_id);
        }
        check_admin_referer('lui_mark_bulk_order_' . $order_id);
        $order = wc_get_order($order_id);
        if (!$order) {
            $fail('Order not found.', $order_id);
        }

        if ($order->get_meta('_lui_is_bulk_order') === 'yes') {
            $this->redirect($order_id);
        }

        $customer_id = $order->get_customer_id();

        if (!$customer_id) {
            $fail('This order has no registered customer.', $order_id);
        }

        if (!get_role('group_head_manager')) {
            $fail('Head Group manager role is not registered.', $order_id);
        }

        // Create seat allocation.
        $this->save_seat_allocation($order, $customer_id);
        // Update customer role.
        $customer = new WP_User($customer_id);

        $customer->set_role('group_head_manager');

        // Save order metadata.
        $order->update_meta_data('_lui_is_bulk_order', 'yes');
        $order->update_meta_data(
            '_lui_bulk_order_marked_by',
            get_current_user_id()
        );
        $order->update_meta_data(
            '_lui_bulk_order_marked_at',
            current_time('mysql')
        );

        $order->save();

        $logger->info(
            'Bulk order approved successfully. Customer ID: ' . $customer_id,
            $source
        );

        $this->redirect($order_id);
    }

    private function redirect(int $order_id): void
    {
        wp_safe_redirect(
            admin_url(
                'admin.php?page=wc-orders&action=edit&id=' . $order_id
            )
        );
        exit;
    }

    private function save_seat_allocation(
        WC_Order $order,
        int $head_manager_user_id
    ): bool {
        global $wpdb;
        $table    = $wpdb->prefix . 'lui_seat_allocations';
        $order_id = $order->get_id();
        $now      = current_time('mysql');

        $order_exists = (bool) $wpdb->get_var($wpdb->prepare("SELECT EXISTS(SELECT 1 FROM {$table} WHERE order_id = %d)", $order_id));

        if ($order_exists) {
            return false;
        }
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if (!$product) {
                continue;
            }
            $quantity = (int) $item->get_quantity();
            if ($quantity <= 0) {
                continue;
            }
            $existing = $wpdb->get_row(
                $wpdb->prepare("SELECT id, reserved_seats, assigned_seats FROM {$table} WHERE order_id = %d LIMIT 1", $order_id)
            );

            $result = $wpdb->insert(
                $table,
                [
                    'head_manager_user_id' => $head_manager_user_id,
                    'group_manager_user_id' => 0,
                    'order_id'              => $order_id,
                    'course_id'             => $product->get_id(),
                    'purchased_seats'       => $quantity,
                    'reserved_seats'        => 0,
                    'assigned_seats'        => 0,
                    'status'                => 'active',
                    'created_at'            => $now,
                    'updated_at'            => $now,
                ],
                ['%d', '%d', '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s']
            );
            $testMe[][$product->get_id()] = $quantity;
        }
        return $result !== false;
    }
}
