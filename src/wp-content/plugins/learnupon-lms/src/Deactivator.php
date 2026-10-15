<?php

declare(strict_types=1);

namespace LearnUponLMS;

/**
 * Deactivation tasks. Not wired in yet: point Plugin::deactivate() here when ready.
 */
final class Deactivator
{
    public static function deactivate(): void
    {
        if (function_exists('as_unschedule_all_actions')) {
            as_unschedule_all_actions('learnupon_lms_process_user_sync', [], 'learnupon-lms');
        }

        flush_rewrite_rules();
    }
}
