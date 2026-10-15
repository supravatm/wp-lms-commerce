<?php

declare(strict_types=1);

namespace LearnUponLMS\Admin\Controllers;

/**
 * admin-post handler for "Test connection".
 *
 * TODO: move from Controllers\AdminActionController::testConnection().
 * Redirect to 'learnupon-lms-settings' (the current default slug does not exist).
 */
final class ConnectionController
{
    public function register(): void
    {
        // add_action('admin_post_learnupon_lms_test_connection', [$this, 'handle']);
    }
}
