<?php

defined('ABSPATH') || exit;

use LearnUponLMS\Models\GroupSeatAllocations;

$userId = get_current_user_id();
$seatAllocation = (new GroupSeatAllocations());
$seatAllotments = $seatAllocation->getAssignedCourses($userId);
$selfCourses = $seatAllocation->getSelfAssinmentCourses($userId);
?>


<h2><?php esc_html_e('My Resorces', 'learnupon-lms'); ?></h2>

<p>
    <?php
    esc_html_e(
        'View your courses and assinee progress here.',
        'learnupon-lms'
    );
    ?>
</p>
<table class="widefat striped">
    <thead>
        <tr>
            <th><?php esc_html_e('Course ID', 'learnupon-lms'); ?></th>
            <th><?php esc_html_e('Course Name', 'learnupon-lms'); ?></th>
            <th><?php esc_html_e('Seat', 'learnupon-lms'); ?></th>
            <th><?php esc_html_e('Remaining Seat', 'learnupon-lms'); ?></th>
        </tr>
    </thead>

    <tbody>
        <?php if (!empty($seatAllotments)) : ?>
            <?php foreach ($seatAllotments as $seatAllotment) :   ?>
                <?php $remainingSeats = $seatAllotment->group_allocated_seats - $seatAllotment->group_assigned_seats; ?>
                <tr>
                    <td><?php echo esc_html((string) $seatAllotment->course_id); ?></td>
                    <td><?php echo esc_html((string) $seatAllotment->order_item_name); ?></td>
                    <td><?php echo esc_html((int) $seatAllotment->group_allocated_seats); ?></td>
                    <td><?php echo esc_html((int) $remainingSeats); ?></td>
                </tr>
            <?php endforeach; ?>
            <?php foreach ($selfCourses as $selfCourse) :   ?>
                <tr>
                    <td><?php echo esc_html((string) $selfCourse->course_id); ?></td>
                    <td><?php echo esc_html((string) $selfCourse->order_item_name); ?></td>
                    <td><?php echo esc_html('-'); ?></td>
                    <td><?php echo esc_html('-'); ?></td>
                </tr>
            <?php endforeach; ?>
        <?php else : ?>
            <tr>
                <td colspan="6">
                    <?php esc_html_e('No assignments found.', 'learnupon-lms'); ?>
                </td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>