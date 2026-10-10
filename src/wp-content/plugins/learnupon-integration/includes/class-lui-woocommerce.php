<?php
if (!defined('ABSPATH')) {
    exit;
}

final class LUI_WooCommerce
{
    public function init(): void
    {
        add_action('woocommerce_order_status_completed', [$this, 'process_completed_order'], 20);
        add_action('learndash_update_group_access', [$this, 'sync_group_user'], 20, 2);
    }

    public function process_completed_order(int $order_id): void
    {
        if (!function_exists('wc_get_order')) {
            return;
        }
        $order = wc_get_order($order_id);
        if (!$order || $order->get_meta('_lui_processed') === 'yes') {
            return;
        }
        $customer_id = (int) $order->get_customer_id();
        if (!$customer_id) {
            LUI_Logger::log('warning', 'Completed order has no registered customer; group setup skipped.', ['order_id' => $order_id]);
            return;
        }
        $created_group_ids = [];
        foreach ($order->get_items() as $item) {
            $product_id = (int) $item->get_product_id();
            $course_id = absint(get_post_meta($product_id, '_lui_course_id', true));
            $group_enabled = get_post_meta($product_id, '_lui_group_purchase_enabled', true) === 'yes';
            if (!$group_enabled) {
                continue;
            }
            if (!post_type_exists('groups') || !function_exists('learndash_set_groups_administrators')) {
                LUI_Logger::log('error', 'LearnDash Groups API is unavailable; order group was not created.', ['order_id' => $order_id, 'product_id' => $product_id]);
                continue;
            }
            $group_id = wp_insert_post([
                'post_type' => 'groups',
                'post_status' => 'publish',
                'post_title' => sprintf('Order %d - %s', $order_id, sanitize_text_field($item->get_name())),
                'post_author' => $customer_id,
            ], true);
            if (is_wp_error($group_id)) {
                LUI_Logger::log('error', 'Could not create LearnDash group for order.', ['order_id' => $order_id, 'product_id' => $product_id]);
                continue;
            }
            learndash_set_groups_administrators($group_id, [$customer_id]);
            if (function_exists('learndash_set_users_group_ids')) {
                learndash_set_users_group_ids($customer_id, array_values(array_unique(array_merge(learndash_get_users_group_ids($customer_id), [$group_id]))));
            }
            update_post_meta($group_id, '_lui_order_id', $order_id);
            update_post_meta($group_id, '_lui_course_id', $course_id);
            update_post_meta($group_id, '_lui_seat_quantity', max(1, (int) $item->get_quantity()));
            $created_group_ids[] = (int) $group_id;
        }
        if ($created_group_ids) {
            $order->update_meta_data('_lui_created_group_ids', $created_group_ids);
            $order->update_meta_data('_lui_processed', 'yes');
            $order->save();
            LUI_Logger::log('info', 'LearnDash groups created for completed order.', ['order_id' => $order_id, 'group_ids' => $created_group_ids]);
        }
    }

    public function sync_group_user($user_id, $group_id): void
    {
        $course_id = absint(get_post_meta((int) $group_id, '_lui_course_id', true));
        if (!$course_id || !get_user_by('id', (int) $user_id)) {
            return;
        }
        $api = new LUI_API_Client();
        $sync = new LUI_User_Sync($api);
        $result = $sync->sync((int) $user_id);
        if (is_wp_error($result)) {
            LUI_Queue::enqueue_user_sync((int) $user_id);
            return;
        }
        $wp_user = get_user_by('id', (int) $user_id);
        $enrollment = $api->enroll_user(['email' => $wp_user->user_email, 'course_id' => $course_id]);
        if (is_wp_error($enrollment)) {
            LUI_Logger::log('error', 'LearnUpon enrollment failed after LearnDash group access change.', ['wp_user_id' => (int) $user_id, 'group_id' => (int) $group_id, 'course_id' => $course_id, 'error' => $enrollment->get_error_code()]);
        }
    }
}
