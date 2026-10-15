<?php

declare(strict_types=1);

namespace LearnUponLMS\Database;

use LearnUponLMS\Models\SeatAllocationInstaller;

/**
 * Versioned schema installer.
 *
 * Delegates to the existing SeatAllocationInstaller for now. Once that class
 * is retired, move its dbDelta() calls here and add numbered files under
 * Database/Migrations for later schema changes.
 */
final class Installer
{
    public const DB_VERSION = '1.0.0';
    private const OPTION = 'learnupon_lms_db_version';

    public static function install(): void
    {
        SeatAllocationInstaller::install();
        update_option(self::OPTION, self::DB_VERSION);
    }

    /**
     * Call on plugins_loaded to run pending migrations after an update.
     */
    public static function maybeUpgrade(): void
    {
        if (get_option(self::OPTION) !== self::DB_VERSION) {
            self::install();
        }
    }
}
