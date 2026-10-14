<?php

namespace LearnUponLMS\Queue;

use LearnUponLMS\Services\ApiClient;
use LearnUponLMS\Services\UserSyncService;
use LearnUponLMS\Util\Logger;

defined('ABSPATH') || exit;

final class UserSyncProcessor
{
    public function processUsersFetch(): void
    {
        $logger = new Logger();

        $context = [
            'source' => 'learnupon-lms-queue',
        ];

        // 1. Call the LearnUpon API.
        $result = (new ApiClient())->getUsers();

        if (!is_array($result)) {
            $logger->error(
                'Unexpected LearnUpon API response.',
                $context
            );

            throw new \RuntimeException(
                'Unexpected LearnUpon API response.'
            );
        }

        // 2. Extract the returned users.
        $users = $result['user'] ?? [];

        if (!is_array($users)) {
            throw new \RuntimeException(
                'Invalid users data returned by LearnUpon.'
            );
        }

        // 3. Save/update users in WordPress.
        $syncResult = (new UserSyncService())->syncUsers($users);

        if (is_wp_error($syncResult)) {
            $logger->error(
                $syncResult->get_error_message(),
                $context
            );

            throw new \RuntimeException(
                $syncResult->get_error_message()
            );
        }

        // 4. Log the completed synchronization.
        $logger->info(
            sprintf(
                'User sync completed. Total: %d, Updated: %d, Failed: %d',
                $syncResult['total'],
                $syncResult['success'],
                $syncResult['failed']
            ),
            $context
        );
    }
}
