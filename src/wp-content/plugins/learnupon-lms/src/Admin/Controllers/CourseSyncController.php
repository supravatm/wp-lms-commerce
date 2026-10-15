<?php

declare(strict_types=1);

namespace LearnUponLMS\Admin\Controllers;

/**
 * admin-post handler for "Fetch courses".
 *
 * TODO: move from Controllers\AdminActionController::fetchCourses().
 * Add pagination so every page of courses is synced, and log via Util\Logger
 * instead of wp-content/product-sync.log.
 */
final class CourseSyncController
{
    public function register(): void
    {
        // add_action('admin_post_learnupon_lms_fetch_courses', [$this, 'handle']);
    }
}
