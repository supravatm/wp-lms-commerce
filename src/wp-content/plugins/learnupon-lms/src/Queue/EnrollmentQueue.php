<?php

declare(strict_types=1);

namespace LearnUponLMS\Queue;

defined('ABSPATH') || exit;

/**
 * Action Scheduler queue for LearnUpon enrollments.
 *
 * TODO: mirror Queue\UserSyncQueue, and remember to call register() from
 * Plugin::init() (UserSyncQueue::register() is currently never called).
 */
final class EnrollmentQueue
{
    private const HOOK = 'learnupon_lms_process_enrollment';
    private const GROUP = 'learnupon-lms';

    public function register(): void
    {
        // add_action(self::HOOK, [$this, 'process'], 10, 1);
    }
}
