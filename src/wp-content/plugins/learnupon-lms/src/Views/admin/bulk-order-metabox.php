<?php
defined('ABSPATH') || exit;
?>

<?php if ($is_bulk_order) : ?>

    <?php
    $customer_id = $order->get_customer_id();
    $user = $customer_id ? get_userdata($customer_id) : false;

    $customer_name = $user ? $user->display_name : '';
    $role_names = $user ? $user->roles : [];
    $role_name = !empty($role_names) ? implode(', ', $role_names) : '';
    ?>
    <p><strong><?php esc_html_e('Bulk order approved', 'learnupon-lms'); ?></strong></p>

    <p><?php esc_html_e(sprintf(__('Customer Name: %s', 'learnupon-lms'), $customer_name)); ?></p>
    <p><?php esc_html_e(sprintf(__('Customer Role: %s', 'learnupon-lms'), $role_name)); ?></p>
<?php else : ?>

    <p><?php esc_html_e('Approve this order as a bulk order and change the customer group.', 'learnupon-lms'); ?></p>
    <a class="button button-primary"
        href="<?php
                echo esc_url(
                    wp_nonce_url(
                        admin_url('admin-post.php?action=learnupon_lms_mark_bulk_order&order_id=' . $order->get_id()),
                        'learnupon_lms_mark_bulk_order_' . $order->get_id()
                    )
                );
                ?>">
        Mark as Bulk Order
    </a>

<?php endif; ?>