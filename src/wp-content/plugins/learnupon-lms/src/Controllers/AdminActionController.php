<?php

declare(strict_types=1);

namespace LearnUponLMS\Controllers;

use Error;
use LearnUponLMS\Services\ApiClient;
use LearnUponLMS\Services\ProductSyncService;
use LearnUponLMS\Services\UserSyncService;

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
                'lui-learnupon-lms-data'
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
        // Check permissions and nonce.
        $this->authorize('learnupon_lms_fetch_users');

        // Read the submitted page number.
        $page = max(1, absint(wp_unslash($_POST['page'] ?? 1)));

        // Call the LearnUpon API client.
        $result = (new ApiClient())->getUsers($page);

        if (!is_array($result)) {
            $this->redirectNotice(
                'error',
                __('Unexpected LearnUpon API response.', 'learnupon-lms'),
                'learnupon-lms-users'
            );
        }
        $users = $result['user'] ?? [];
        $syncResult = (new UserSyncService())->syncUsers($users);
        if (is_wp_error($syncResult)) {
            $this->redirectNotice(
                'error',
                $syncResult->get_error_message(),
                'lui-learnupon-lms-data'
            );
        }
        $message = sprintf(
            'User sync completed. Total: %1$d, Updated: %2$d, Failed: %3$d',
            $syncResult['total'],
            $syncResult['success'],
            $syncResult['failed']
        );
        $this->redirectNotice(
            'success',
            $message,
            'learnupon-lms-users'
        );
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
        string $page = 'lui-learnupon-lms'
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
