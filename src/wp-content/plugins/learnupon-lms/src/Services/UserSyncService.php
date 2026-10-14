<?php

declare(strict_types=1);

namespace LearnUponLMS\Services;

use LearnUponLMS\Services\UserSync;

final class UserSyncService
{
    public function syncUsers(array $users): array|\WP_Error
    {
        $results = [
            'total'   => count($users),
            'success' => 0,
            'failed'  => 0,
            'errors'  => [],
        ];
        foreach ($users as $user) {
            if (!is_array($user)) {
                $results['failed']++;
                $results['errors'][] = __(
                    'Invalid course data.',
                    'learnupon-lms'
                );
                continue;
            }
            $userId = (new UserSync())->sync($user);
            if (is_wp_error($userId)) {
                $results['failed']++;
                $results['errors'][] = $userId->get_error_message();

                continue;
            }
            $results['success']++;
        }
        return $results;
    }
}
