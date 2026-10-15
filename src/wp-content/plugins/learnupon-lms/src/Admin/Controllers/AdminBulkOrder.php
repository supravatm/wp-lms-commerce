<?php

declare(strict_types=1);

namespace LearnUponLMS\Controllers;

use Error;
use LearnUponLMS\Models\SeatAllocationRepository;
use LearnUponLMS\Models\OrderSeatTracker;
use LearnUponLMS\Models\OrderSeatAssignment;
use LearnUponLMS\Util\Logger;

class AdminBulkOrder
{
    public function register(): void
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
            'admin_post_learnupon_lms_mark_bulk_order',
            [$this, 'mark_bulk_order']
        );
    }
    public function register_legacy_metabox(): void
    {
        add_meta_box(
            'learn_lms_bulk_order',
            'LearnUpon Bulk Order',
            [$this, 'render_metabox'],
            'shop_order',
            'side',
            'high'
        );
    }

    public function register_hpos_metabox(): void
    {
        add_meta_box(
            'learn_lms_bulk_order',
            'LearnUpon Bulk Order',
            [$this, 'render_metabox'],
            'woocommerce_page_wc-orders',
            'side',
            'high'
        );
    }

    /**
     * Render the bulk order metabox.
     *
     * @param \WC_Order|\WP_Post $post_or_order Order object or post object.
     * @return void
     */
    public function render_metabox($post_or_order): void
    {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        $order_id = $post_or_order instanceof \WC_Order
            ? $post_or_order->get_id()
            : absint($post_or_order->ID ?? 0);

        $order = wc_get_order($order_id);
        $is_bulk_order = $order->get_meta('_learnupon_lms_is_bulk_order') === 'yes';
        require LEARNUPON_LMS_PATH . 'src/Views/admin/bulk-order-metabox.php';
    }

    public function mark_bulk_order(): void
    {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }
        $logger = wc_get_logger();
        $source = ['source' => 'learnupon_lms_mark_bulk_order'];
        $order_id = absint($_GET['order_id'] ?? 0);
        $isSelfAssignment = false;

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
        check_admin_referer('learnupon_lms_mark_bulk_order_' . $order_id);
        $order = wc_get_order($order_id);

        if ($order->get_meta('_learnupon_lms_is_bulk_order') === 'yes') {
            $this->redirect($order_id);
        }

        // TODO: Update customer role.
        $user_id = $order->get_customer_id();
        if ($user_id > 0) {
            $user = new \WP_User($user_id);
            $allowed_roles = ['learner', 'instructor', 'manager'];
            if (array_intersect($allowed_roles, $user->roles)) {
                $isSelfAssignment = true;
            }
        }
        try {
            // TODO: Create seat allocation.
            foreach ($order->get_items() as $item_id => $item) {
                $product_id = (int) $item->get_product_id();
                $product_id = (int) $item->get_product_id();
                $product = wc_get_product($product_id);
                $data = [
                    'order_id' => $order_id,
                    'order_item_id' => $item_id,
                    'head_manager_user_id' => $order->get_customer_id(),
                    'product_id' => $product_id,
                    'course_id' => $product ? $product->get_sku() : '',
                    'purchased_seats' => $item->get_quantity(),
                    'allocated_seats' => 0,
                    'status' => 'active',
                ];
                $trackerId = (new OrderSeatTracker)->create($data);
                if ($isSelfAssignment && $trackerId) {
                    // TODO: Seat Assignment
                    $seatAssignment = [
                        'tracker_id' => $trackerId,
                        'group_allocation_id' => 0,
                        'group_member_user_id' => $order->get_customer_id(),
                        'assigned_by' => $order->get_customer_id(),
                        'status' => 'active'
                    ];
                    // TODO:Direct assignment for learner
                    (new OrderSeatAssignment)->create($seatAssignment);
                }
            }

            // Save order metadata.
            $order->update_meta_data('_learnupon_lms_is_bulk_order', 'yes');
            $order->update_meta_data('_learnupon_lms_bulk_order_marked_by', get_current_user_id());
            $order->update_meta_data('_learnupon_lms_bulk_order_marked_at', current_time('mysql'));
            $order->save();

            Logger::info('Bulk order approved successfully.', ['order_id' => $order_id,]);
        } catch (\RuntimeException $e) {
            Logger::error('Failed to create tracker ', ['order_id' => $order_id,]);
        }
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
}
