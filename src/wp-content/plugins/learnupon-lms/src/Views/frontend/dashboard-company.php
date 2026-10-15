<?php

defined('ABSPATH') || exit;

use LearnUponLMS\Models\OrderSeatTracker;

$userId = get_current_user_id();
$orderSeatTracker = (new OrderSeatTracker());
$companySeats = $orderSeatTracker->getByCompanyUserId($userId);
?>
<h2><?php esc_html_e('My Company', 'learnupon-lms'); ?></h2>
<p>
    <?php
    esc_html_e(
        'View your Company Resources here.',
        'learnupon-lms'
    );
    ?>
</p>
<table class="widefat striped">
    <thead>
        <tr>
            <th><?php esc_html_e('Course ID', 'learnupon-lms'); ?></th>
            <th><?php esc_html_e('Course Name', 'learnupon-lms'); ?></th>
            <th><?php esc_html_e('Purchased Seat', 'learnupon-lms'); ?></th>
            <th><?php esc_html_e('Allocated Seat', 'learnupon-lms'); ?></th>
            <th><?php esc_html_e('Available Seat', 'learnupon-lms'); ?></th>
        </tr>
    </thead>

    <tbody>
        <?php if (!empty($companySeats)) : ?>
            <?php foreach ($companySeats as $companySeat) : ?>
                <tr>
                    <td><?php echo esc_html((string) $companySeat->course_id); ?></td>
                    <td><?php echo esc_html((string) $companySeat->order_item_name); ?></td>
                    <td><?php echo esc_html((int) $companySeat->purchased_seats); ?></td>
                    <td><?php echo esc_html((int) $companySeat->allocated_seats); ?></td>
                    <td><?php echo esc_html((int) $companySeat->purchased_seats - $companySeat->allocated_seats); ?></td>
                </tr>
            <?php endforeach; ?>
        <?php else : ?>
            <tr>
                <td colspan="6">
                    <?php esc_html_e('No Seat found.', 'learnupon-lms'); ?>
                </td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>