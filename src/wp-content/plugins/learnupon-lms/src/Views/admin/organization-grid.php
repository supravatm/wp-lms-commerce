<?php

defined('ABSPATH') || exit;

/** @var \LearnUponLMS\Admin\OrganizationListTable $table */

if (!current_user_can('manage_options')) {
    return;
}
?>

<div class="wrap">
    <h1 class="wp-heading-inline">
        <?php esc_html_e('Organizations', 'learnupon-lms'); ?>
    </h1>

    <hr class="wp-header-end">

    <p>
        <?php
        esc_html_e(
            'Manage Organization.',
            'learnupon-lms'
        );
        ?>
    </p>

    <form method="get">
        <input
            type="hidden"
            name="page"
            value="learnupon-lms-organization" />

        <?php
        $table->search_box(
            __('Search Organization', 'learnupon-lms'),
            'organization'
        );

        $table->display();
        ?>
    </form>
</div>