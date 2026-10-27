<?php

declare(strict_types=1);

namespace LearnUponLMS\Admin\Controllers;

use Error;
use LearnUponLMS\Services\ApiClient;
use LearnUponLMS\Services\ProductSyncService;
use LearnUponLMS\Services\UserSyncService;
use LearnUponLMS\Queue\UserSyncQueue;
use LearnUponLMS\Services\UserSync;

final class AdminActionController
{
    public function register(): void
    {
        $hookName = 'admin_post_learnupon_lms_';
        add_action(
            $hookName . 'test_connection',
            [$this, 'testConnection']
        );
        add_action(
            $hookName . 'fetch_courses',
            [$this, 'fetchCourses']
        );
        add_action(
            $hookName . 'fetch_users',
            [$this, 'fetchUsers']
        );
    }
    public function testConnection(): void
    {
        $this->authorize('learnupon_lms_test_connection');

        $result = (new ApiClient())->testConnection();

        if (is_wp_error($result)) {
            $this->redirectNotice(
                'error',
                $result->get_error_message()
            );
        }

        $this->redirectNotice(
            'success',
            __('LearnUpon API connection successful.', 'learnupon-lms')
        );
    }
    public function fetchCourses(): void
    {
        // Check permissions and nonce.
        $this->authorize('learnupon_lms_fetch_courses');

        // Read the submitted page number.
        $page = max(1, absint(wp_unslash($_POST['page'] ?? 1)));

        // Call the LearnUpon API client.
        $result = (new ApiClient())->getCourses($page);

        if (!is_array($result)) {
            $this->redirectNotice(
                'error',
                __('Unexpected LearnUpon API response.', 'learnupon-lms'),
                'learnupon-lms-courses'
            );
        }
        $courses = $result['courses'] ?? [];
        $syncResult = (new ProductSyncService())->syncCourses($courses);
        if (is_wp_error($syncResult)) {
            $this->redirectNotice(
                'error',
                $syncResult->get_error_message(),
                'learnupon-lms-courses'
            );
        }
        $message = sprintf(
            'Course sync completed. Total: %1$d, Updated: %2$d, Failed: %3$d',
            $syncResult['total'],
            $syncResult['success'],
            $syncResult['failed']
        );
        $this->redirectNotice(
            'success',
            $message,
            'learnupon-lms-courses'
        );
    }

    public function fetchUsers(): void
    {
        try {
            // Check permissions and nonce.
            $this->authorize('learnupon_lms_fetch_users');

            // Read the submitted page number.
            $identifier = isset($_POST['user_identifier'])
                ? trim(sanitize_text_field(wp_unslash($_POST['user_identifier']))) : '';

            if ($identifier === '') {
                $queue = new UserSyncQueue();
                $action_id = $queue->enqueueUserSync();
                if ($action_id === 0) {
                    $this->redirectNotice(
                        'error',
                        __('Unable to enqueue user synchronization.', 'learnupon-lms'),
                        'learnupon-lms-users'
                    );
                }
                $this->redirectNotice(
                    'success',
                    sprintf(
                        __('User synchronization queued. Action ID: %d', 'learnupon-lms'),
                        $action_id
                    ),
                    'learnupon-lms-users'
                );
            } elseif (is_email($identifier)) {
                // Email provided.
                $usser = (new ApiClient())->get_user_by_email($identifier);
                $userId = (new UserSync())->sync($usser);
                if (is_wp_error($usser)) {
                    $this->redirectNotice(
                        'error',
                        __($usser->get_error_message(), 'learnupon-lms'),
                        'learnupon-lms-users'
                    );
                }
                $this->redirectNotice(
                    'success',
                    __('LearnUpon user found.', 'learnupon-lms'),
                    'learnupon-lms-users'
                );
            } elseif (ctype_digit($identifier) && (int) $identifier > 0) {
                // Numeric user ID provided.
                $result = (new ApiClient())->get_user_by_id((int) $identifier);
                if (is_wp_error($result)) {
                    $this->redirectNotice(
                        'error',
                        __($result->get_error_message(), 'learnupon-lms'),
                        'learnupon-lms-users'
                    );
                }
                $this->redirectNotice(
                    'success',
                    __('LearnUpon user found.', 'learnupon-lms'),
                    'learnupon-lms-users'
                );
            } else {
                $this->redirectNotice(
                    'error',
                    __('Enter a valid email address, positive user ID, or leave the field empty.', 'learnupon-lms'),
                    'learnupon-lms-users'
                );
            }
        } catch (\Throwable $e) {
            wc_get_logger()->error(
                $e->getMessage(),
                ['source' => 'learnupon-lms-queue']
            );
            $this->redirectNotice(
                'error',
                __('Unable to schedule user synchronization.', 'learnupon-lms'),
                'learnupon-lms-users'
            );
        }
    }

    private function authorize(string $nonceAction): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(
                esc_html__('You are not allowed to perform this action.', 'learnupon-lms'),
                '',
                ['response' => 403]
            );
        }

        check_admin_referer($nonceAction);
    }

    private function redirectNotice(
        string $type,
        string $message,
        string $page = 'learnupon-lms-settings'
    ): void {
        set_transient(
            'learnupon_lms_admin_notice_' . get_current_user_id(),
            [
                'type' => $type,
                'message' => $message,
            ],
            30
        );

        wp_safe_redirect(
            admin_url('admin.php?page=' . $page)
        );
        exit;
    }
}
