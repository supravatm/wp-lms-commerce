<?php

declare(strict_types=1);

namespace LearnUponLMS;

use LearnUponLMS\Core\Roles;
use LearnUponLMS\Database\Installer;

/**
 * Activation tasks. Not wired in yet: point Plugin::activate() here when ready.
 */
final class Activator
{
    public static function activate(): void
    {
        Installer::install();
        Roles::register();

        // Endpoints must be registered before flushing, otherwise they 404.
        (new Frontend\MyAccount())->addEndpoint();
        flush_rewrite_rules();
    }
}
