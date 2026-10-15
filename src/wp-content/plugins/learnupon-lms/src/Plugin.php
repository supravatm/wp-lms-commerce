<?php

declare(strict_types=1);

namespace LearnUponLMS;

use LearnUponLMS\Admin\AdminPage;
use LearnUponLMS\Core\Dependencies;
use LearnUponLMS\Database\Installer;
use LearnUponLMS\Frontend\MyAccount;
use LearnUponLMS\Queue\UserSyncQueue;

final class Plugin
{
    private static ?self $instance = null;

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public static function activate(): void
    {
        Activator::activate();
    }

    public static function deactivate(): void
    {
        Deactivator::deactivate();
    }

    public function init(): void
    {
        Installer::maybeUpgrade();

        if (!Dependencies::satisfied()) {
            add_action('admin_notices', [$this, 'missingDependenciesNotice']);
            return;
        }

        (new UserSyncQueue())->register();
        (new MyAccount())->register();

        if (is_admin()) {
            (new AdminPage())->register();
        }
    }

    public function missingDependenciesNotice(): void
    {
        if (!current_user_can('activate_plugins')) {
            return;
        }

        printf(
            '<div class="notice notice-error"><p>%s</p></div>',
            esc_html(sprintf(
                /* translators: %s is a comma-separated list of plugin names. */
                __('LearnUpon LMS requires: %s.', 'learnupon-lms'),
                implode(', ', Dependencies::missing())
            ))
        );
    }
}
