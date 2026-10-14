<?php

declare(strict_types=1);

namespace LearnUponLMS;

use LearnUponLMS\Admin\AdminPage;
use LearnUponLMS\Frontend\MyAccount;
use LearnUponLMS\Models\SeatAllocationInstaller;

final class Plugin
{
    private static ?self $instance = null;

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public static function activate(): void
    {
        // Create database tables or perform setup if required.
        SeatAllocationInstaller::install();
    }

    public static function deactivate(): void
    {
        // Clear scheduled events if required.
    }

    public function init(): void
    {
        // Register frontend functionality on every request.
        (new MyAccount())->register();

        if (is_admin()) {
            (new AdminPage())->register();
        }
    }
}
