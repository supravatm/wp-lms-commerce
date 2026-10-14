<?php

defined('ABSPATH') || exit;

/** @var \LearnUponLMS\Admin\SeatAllocationsListTable $table */

if (!current_user_can('manage_options')) {
    return;
}
?>

<div class="wrap">
    <h1 class="wp-heading-inline">
        <?php esc_html_e('Seat Allocations', 'learnupon-lms'); ?>
    </h1>

    <hr class="wp-header-end">

    <p>
        <?php
        esc_html_e(
            'Manage purchased seats, group manager allocations, and member assignments.',
            'learnupon-lms'
        );
        ?>
    </p>

    <form method="get">
        <input
            type="hidden"
            name="page"
            value="learnupon-lms-seat-allocation" />

        <?php
        $table->search_box(
            __('Search Seat Allocations', 'learnupon-lms'),
            'seat-allocation'
        );

        $table->display();
        ?>
    </form>
</div>