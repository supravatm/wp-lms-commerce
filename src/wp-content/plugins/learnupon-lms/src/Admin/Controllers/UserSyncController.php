<?php

declare(strict_types=1);

namespace LearnUponLMS\Admin\Controllers;

/**
 * admin-post handler for "Fetch users".
 *
 * TODO: move from Controllers\AdminActionController::fetchUsers().
 * The single-user lookup branches (email / ID) currently drop their result
 * and never redirect; give each branch a success notice.
 */
final class UserSyncController
{
    public function register(): void
    {
        // add_action('admin_post_learnupon_lms_fetch_users', [$this, 'handle']);
    }
}
