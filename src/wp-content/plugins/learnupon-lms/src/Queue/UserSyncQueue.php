<?php

declare(strict_types=1);

namespace LearnUponLMS\Queue;

defined('ABSPATH') || exit;
class UserSyncQueue
{
    private const HOOK = 'learnupon_lms_process_user_sync';
    private const GROUP = 'learnupon-lms';

    public function register(): void
    {
        add_action(self::HOOK, [$this, 'processUserSync'], 10, 1);
    }

    public function enqueueUserSync(): int
    {
        if (!function_exists('as_enqueue_async_action')) {
            throw new \RuntimeException(
                'Action Scheduler is not available.'
            );
        }
        return as_enqueue_async_action(
            self::HOOK,
            [],
            self::GROUP,
            false
        );
    }

    public function processUserSync(): void
    {
        $processor = new UserSyncProcessor();
        $processor->processUsersFetch();
    }
}
