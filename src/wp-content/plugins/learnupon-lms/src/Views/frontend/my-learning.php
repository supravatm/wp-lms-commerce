<?php

defined('ABSPATH') || exit;

use LearnUponLMS\Models\OrderSeatAssignment;

$userId = get_current_user_id();
$orderSeatAssignment = (new OrderSeatAssignment());
$assignments = $orderSeatAssignment->getCourseByGroupMemberId($userId);
$selfAssignments = $orderSeatAssignment->getCourseAssignedBySelf($userId);
?>
<h2><?php esc_html_e('My Learning', 'learnupon-lms'); ?></h2>

<p>
    <?php
    esc_html_e(
        'View your courses and learning progress here.',
        'learnupon-lms'
    );
    ?>
</p>

<?php if (!empty($assignments) || !empty($selfAssignments)) : ?>
    <table class="widefat striped">
        <thead>
            <tr>
                <th><?php esc_html_e('Course ID', 'learnupon-lms'); ?></th>
                <th><?php esc_html_e('Course Name', 'learnupon-lms'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($assignments as $assignment) : ?>
                <?php echo '<pre>';
                // echo esc_html(wp_json_encode($assignment, JSON_PRETTY_PRINT));
                echo '</pre>'; ?>
                <tr>
                    <td><?php echo esc_html((string) $assignment->course_id); ?></td>
                    <td><?php echo esc_html((string) $assignment->order_item_name); ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!empty($selfAssignments)) : ?>
                <?php foreach ($assignments as $assignment) : ?>
                    <?php echo '<pre>';
                    // echo esc_html(wp_json_encode($assignment, JSON_PRETTY_PRINT));
                    echo '</pre>'; ?>
                    <tr>
                        <td><?php echo esc_html((string) $assignment->course_id); ?></td>
                        <td><?php echo esc_html((string) $assignment->order_item_name); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
<?php else : ?>
    <tr>
        <td colspan="6">
            <?php esc_html_e('No assignments found.', 'learnupon-lms'); ?>
        </td>
    </tr>
<?php endif; ?>