<?php

declare(strict_types=1);

namespace LearnUponLMS\Admin;

/**
 * Adds the LearnUpon user ID row to the WordPress user profile screen.
 *
 * TODO: move from Controllers\AdminUser.
 */
final class UserProfile
{
    public function register(): void
    {
        // add_action('show_user_profile', [$this, 'render']);
        // add_action('edit_user_profile', [$this, 'render']);
    }
}
