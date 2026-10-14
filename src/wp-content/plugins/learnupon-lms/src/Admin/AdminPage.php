<?php

declare(strict_types=1);

namespace LearnUponLMS\Admin;

use LearnUponLMS\Controllers\AdminActionController;
use LearnUponLMS\Controllers\AdminBulkOrder;
use LearnUponLMS\Controllers\AdminUser;
use LearnUponLMS\Models\SeatAllocationsListTable;

class AdminPage
{
    public function register(): void
    {
        add_action('admin_menu', [$this, 'addMenu']);
        add_action('admin_init', [$this, 'registerSettings']);
        add_action('admin_notices', [$this, 'displayNotice']);
        (new AdminActionController())->register();
        (new AdminBulkOrder())->register();
        (new AdminUser())->register();
    }
    public function registerSettings(): void
    {
        register_setting(
            'learnupon_lms_settings_group',
            'learnupon_lms_settings',
            [$this, 'sanitizeSettings']
        );
    }

    public function addMenu(): void
    {
        add_menu_page(
            'LearnUpon LMS',
            'LearnUpon LMS',
            'manage_options',
            'learnupon-lms-settings',
            [$this, 'renderSettings'],
            'dashicons-admin-plugins'
        );
        add_submenu_page(
            'learnupon-lms-settings',
            __('Courses', 'learnupon-lms'),
            __('Courses', 'learnupon-lms'),
            'manage_options',
            'learnupon-lms-courses',
            [$this, 'renderCourses']
        );
        add_submenu_page(
            'learnupon-lms-settings',
            __('Users', 'learnupon-lms'),
            __('Users', 'learnupon-lms'),
            'manage_options',
            'learnupon-lms-users',
            [$this, 'renderUsers']
        );
        add_submenu_page(
            'learnupon-lms-settings',
            __('Seat Allocations', 'learnupon-lms'),
            __('Seat Allocations', 'learnupon-lms'),
            'manage_options',
            'learnupon-lms-seat-allocation',
            [$this, 'renderSeatAllocation']
        );

        add_submenu_page(
            'learnupon-lms-settings',
            __('LearnUpon Queue Test', 'learnupon-lms'),
            __('LearnUpon Queue Test', 'learnupon-lms'),
            'manage_options',
            'learnupon-lms-queue-test',
            [$this, 'learnupon_lms_render_queue_test']
        );
    }

    /**
     * Render the Seat Allocations admin page.
     */
    public function renderSeatAllocation(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(
                esc_html__(
                    'You do not have permission to view seat allocations.',
                    'learnupon-lms'
                )
            );
        }

        $table = new SeatAllocationsListTable();
        $table->prepare_items();

        require LEARNUPON_LMS_PATH . 'src/Views/admin/seat-allocations.php';
    }
    public function renderSettings(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        require LEARNUPON_LMS_PATH . 'src/Views/admin/settings-page.php';
    }
    public function renderCourses(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        require LEARNUPON_LMS_PATH . 'src/Views/admin/courses.php';
    }
    public function renderUsers(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        require LEARNUPON_LMS_PATH . 'src/Views/admin/users.php';
    }

    public function displayNotice(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $key = 'learnupon_lms_admin_notice_' . get_current_user_id();
        $notice = get_transient($key);

        if (!is_array($notice)) {
            return;
        }

        delete_transient($key);

        $allowedTypes = ['success', 'error', 'warning', 'info'];
        $type = in_array($notice['type'] ?? '', $allowedTypes, true)
            ? $notice['type']
            : 'info';

        printf(
            '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
            esc_attr($type),
            esc_html((string) ($notice['message'] ?? ''))
        );
    }
    public function sanitizeSettings(mixed $input): array
    {
        $input = is_array($input) ? $input : [];
        $old   = get_option('learnupon_lms_settings', []);
        $portalUrl = esc_url_raw(trim((string) ($input['portal_url'] ?? '')));
        $username  = sanitize_text_field(trim((string) ($input['api_username'] ?? '')));
        $password  = (string) ($input['api_password'] ?? '');
        $errors = [];

        // Validate portal URL.
        if (
            $portalUrl === ''
            || !wp_http_validate_url($portalUrl)
            || wp_parse_url($portalUrl, PHP_URL_SCHEME) !== 'https'
        ) {
            $errors[] = __('Enter a valid HTTPS LearnUpon portal URL.', 'learnupon-lms');
        }

        // Validate API username.
        if ($username === '') {
            $errors[] = __('API username is required.', 'learnupon-lms');
        }

        // Preserve existing password if the field is left blank.
        if ($password === '') {
            $password = (string) ($old['api_password'] ?? '');
        }

        if ($password === '') {
            $errors[] = __('API password is required.', 'learnupon-lms');
        }

        // Display validation errors and retain existing settings.
        if ($errors !== []) {
            foreach ($errors as $error) {
                add_settings_error(
                    'learnupon_lms_settings',
                    'invalid_settings',
                    $error,
                    'error'
                );
            }

            return is_array($old) ? $old : [];
        }

        $settings = [
            'portal_url'   => untrailingslashit($portalUrl),
            'api_username' => $username,
            'api_password' => $password,
        ];

        set_transient(
            'learnupon_lms_admin_notice_' . get_current_user_id(),
            [
                'type'    => 'success',
                'message' => __('LearnUpon settings saved successfully.', 'learnupon-lms'),
            ],
            60
        );

        return $settings;
    }


    // Create a temporary test trigger for queue

    function learnupon_lms_render_queue_test(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (
            isset($_POST['learnupon_lms_queue_test']) &&
            check_admin_referer('learnupon_lms_queue_test_action')
        ) {
            $user_id = isset($_POST['user_id'])
                ? absint($_POST['user_id'])
                : 0;

            try {
                $queue = new \LearnUponLMS\Queue\UserSyncQueue();
                $action_id = $queue->enqueueUserSync($user_id);

                echo '<div class="notice notice-success"><p>';
                echo esc_html('Queue action ID: ' . $action_id);
                echo '</p></div>';
            } catch (\Throwable $e) {
                echo '<div class="notice notice-error"><p>';
                echo esc_html($e->getMessage());
                echo '</p></div>';
            }
        }
?>
        <div class="wrap">
            <h1>LearnUpon Queue Test</h1>

            <form method="post">
                <?php wp_nonce_field('learnupon_lms_queue_test_action'); ?>

                <p>
                    <label for="user_id">WordPress User ID</label>
                    <input
                        type="number"
                        id="user_id"
                        name="user_id"
                        min="1"
                        required>
                </p>

                <button
                    type="submit"
                    name="learnupon_lms_queue_test"
                    value="1"
                    class="button button-primary">
                    Enqueue Test
                </button>
            </form>
        </div>
<?php
    }
}
