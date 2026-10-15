<?php

declare(strict_types=1);

namespace LearnUponLMS\Admin;

/**
 * Settings screen: menu page, register_setting() and sanitizeSettings().
 *
 * TODO: move these out of Admin\AdminPage, which currently handles every
 * screen plus the queue test page.
 */
final class SettingsPage
{
    public function register(): void
    {
        // add_action('admin_init', [$this, 'registerSettings']);
    }
}
