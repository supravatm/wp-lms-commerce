<?php
if (!defined('ABSPATH')) {
    exit;
}

final class LUI_Admin
{
    public function init(): void
    {
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_post_lui_test_connection', [$this, 'test_connection']);
        add_action('admin_post_lui_sync_user', [$this, 'sync_user']);
        add_action('admin_post_lui_fetch_courses', [$this, 'fetch_courses']);
        add_action('admin_post_lui_fetch_enrollments', [$this, 'fetch_enrollments']);
        add_action('admin_notices', [$this, 'display_notice']);
    }
    public function menu(): void
    {
        add_menu_page(__('LearnUpon Integration', 'learnupon-integration'), __('LearnUpon', 'learnupon-integration'), 'manage_options', 'lui-settings', [$this, 'settings_page'], 'dashicons-welcome-learn-more');
        add_submenu_page('lui-settings', __('Users', 'learnupon-integration'), __('User Sync', 'learnupon-integration'), 'manage_options', 'lui-users', [$this, 'users_page']);
        add_submenu_page('lui-settings', __('Courses & Enrollments', 'learnupon-integration'), __('Courses & Enrollments', 'learnupon-integration'), 'manage_options', 'lui-data', [$this, 'data_page']);
    }
    public function register_settings(): void
    {
        register_setting('lui_settings_group', 'lui_settings', [$this, 'sanitize_settings']);
    }
    public function sanitize_settings($input): array
    {
        $old = get_option('lui_settings', []);
        $portal = isset($input['portal_url']) ? esc_url_raw(trim((string) $input['portal_url'])) : '';
        if ($portal && wp_parse_url($portal, PHP_URL_SCHEME) !== 'https') {
            add_settings_error('lui_settings', 'lui_https_required', __('LearnUpon portal URL must use HTTPS.', 'learnupon-integration'));
            $portal = '';
        }
        return [
            'portal_url' => untrailingslashit($portal),
            'api_username' => sanitize_text_field($input['api_username'] ?? ''),
            'api_password' => isset($input['api_password']) && (string) $input['api_password'] !== '' ? sanitize_text_field((string) $input['api_password']) : (string) ($old['api_password'] ?? ''),
        ];
    }
    public function settings_page(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        $s = get_option('lui_settings', []);
?>
        <div class="wrap">
            <h1>LearnUpon Integration</h1>
            <p>Configure your LearnUpon API connection. Use API keys created for integrations, not your normal LearnUpon login.</p>
            <?php settings_errors('lui_settings'); ?>
            <form method="post" action="options.php">
                <?php settings_fields('lui_settings_group'); ?>
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th><label for="lui_portal_url">Portal URL</label></th>
                            <td><input class="regular-text" type="url" id="lui_portal_url" name="lui_settings[portal_url]" value="<?php echo esc_attr($s['portal_url'] ?? ''); ?>" placeholder="https://yourportal.learnupon.com" required>
                                <p class="description">Enter the portal origin only, without /api/v1.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="lui_api_username">API username</label></th>
                            <td><input class="regular-text" type="text" autocomplete="off" id="lui_api_username" name="lui_settings[api_username]" value="<?php echo esc_attr($s['api_username'] ?? ''); ?>" required></td>
                        </tr>
                        <tr>
                            <th><label for="lui_api_password">API password</label></th>
                            <td><input class="regular-text" type="password" autocomplete="new-password" id="lui_api_password" name="lui_settings[api_password]" value="" placeholder="<?php echo !empty($s['api_password']) ? esc_attr__('Saved; leave blank to keep it', 'learnupon-integration') : esc_attr__('Enter API password', 'learnupon-integration'); ?>">
                                <p class="description">Stored in the WordPress options table. Restrict database/admin access and use environment-based secrets for production.</p>
                            </td>
                        </tr>
                    </tbody>
                </table><?php submit_button(__('Save settings', 'learnupon-integration')); ?>
            </form>
            <hr>
            <h2>Connection test</h2>
            <p>Tests API authentication by requesting the first page of courses.</p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><?php wp_nonce_field('lui_test_connection'); ?><input type="hidden" name="action" value="lui_test_connection"><?php submit_button(__('Test LearnUpon connection', 'learnupon-integration'), 'secondary'); ?></form>
        </div><?php
            }
            public function users_page(): void
            {
                if (!current_user_can('manage_options')) {
                    return;
                }
                ?> <div class="wrap">
            <h1>WordPress → LearnUpon user sync</h1>
            <p>Enter a WordPress user ID to create/update that learner in LearnUpon. A random LearnUpon password is generated for new accounts; it is never copied from WordPress.</p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><?php wp_nonce_field('lui_sync_user'); ?><input type="hidden" name="action" value="lui_sync_user"><label for="lui_user_id">WordPress user ID </label><input type="number" min="1" required id="lui_user_id" name="user_id"><button class="button button-primary">Sync user</button></form>
        </div><?php
            }
            public function data_page(): void
            {
                if (!current_user_can('manage_options')) {
                    return;
                }
                $courses = get_transient('lui_last_courses');
                $enrollments = get_transient('lui_last_enrollments');
                ?> <div class="wrap">
            <h1>Courses & Enrollments</h1>
            <p>Fetch a page of LearnUpon courses or view enrollments for an email address.</p>
            <form style="margin-bottom:16px" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><?php wp_nonce_field('lui_fetch_courses'); ?><input type="hidden" name="action" value="lui_fetch_courses"><label for="lui_course_page">Page </label><input type="number" id="lui_course_page" name="page" min="1" value="1"><button class="button button-primary">Fetch courses</button></form>
            <form style="margin-bottom:16px" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><?php wp_nonce_field('lui_fetch_enrollments'); ?><input type="hidden" name="action" value="lui_fetch_enrollments"><label for="lui_enrollment_email">Learner email </label><input type="email" id="lui_enrollment_email" name="email" required><button class="button">Fetch enrollments</button></form>

            <?php if (is_array($courses)) {
                    echo '<h2>Last fetched course response</h2><pre style="max-width:900px;overflow:auto">' . esc_html(wp_json_encode($courses, JSON_PRETTY_PRINT)) . '</pre>';
                } ?>
            <?php if (is_array($enrollments)) {
                    echo '<h2>Last fetched enrollment response</h2><pre style="max-width:900px;overflow:auto">' . esc_html(wp_json_encode($enrollments, JSON_PRETTY_PRINT)) . '</pre>';
                } ?>
            <p>Enrollment lookup is available through the API client and can be wired into a dedicated search form after confirming the fields your portal returns.</p>
        </div><?php
            }
            public function test_connection(): void
            {
                $this->authorize('lui_test_connection');
                $result = (new LUI_API_Client())->test_connection();
                $this->redirect_notice(is_wp_error($result) ? 'error' : 'success', is_wp_error($result) ? $result->get_error_message() : __('Connection successful. LearnUpon API authentication works.', 'learnupon-integration'));
            }
            public function sync_user(): void
            {
                $this->authorize('lui_sync_user');
                $user_id = absint($_POST['user_id'] ?? 0);
                $result = (new LUI_User_Sync())->sync($user_id);
                if (is_wp_error($result)) {
                    LUI_Queue::enqueue_user_sync($user_id);
                    $this->redirect_notice('error', $result->get_error_message() . ' A retry has been queued.');
                }
                $this->redirect_notice('success', __('User sync request completed.', 'learnupon-integration'));
            }
            public function fetch_courses(): void
            {
                $this->authorize('lui_fetch_courses');
                $page = max(1, absint($_POST['page'] ?? 1));
                $result = (new LUI_API_Client())->get_courses($page);
                if (is_wp_error($result)) {
                    $this->redirect_notice('error', $result->get_error_message());
                }
                set_transient('lui_last_courses', $result, 10 * MINUTE_IN_SECONDS);
                // Adjust this key to match the actual API response.
                // $data = json_decode($result, true);
                $courses = $result['courses'] ?? [];

                if (!is_array($courses)) {
                    $this->redirect_notice('error', 'Unexpected LearnUpon courses response.');
                    return;
                }
                $sync = new LUI_Product_Sync();
                $created_or_updated = 0;
                $errors = [];

                foreach ($courses as $course) {
                    if (!is_array($course)) {
                        continue;
                    }

                    $product_id = $sync->sync($course);

                    if (is_wp_error($product_id)) {
                        $errors[] = $product_id->get_error_message();
                        continue;
                    }
                    $created_or_updated++;
                }

                if ($errors) {
                    $this->redirect_notice('error', sprintf('%d products created/updated. Errors: %s', $created_or_updated, implode('; ', array_unique($errors))), 'lui-data');
                    return;
                }
                $this->redirect_notice('success', __('Courses fetched. See the response below.', 'learnupon-integration'), 'lui-data');
            }
            public function fetch_enrollments(): void
            {
                $this->authorize('lui_fetch_enrollments');
                $email = sanitize_email(wp_unslash($_POST['email'] ?? ''));
                if (!is_email($email)) {
                    $this->redirect_notice('error', __('Enter a valid learner email address.', 'learnupon-integration'), 'lui-data');
                }
                $result = (new LUI_API_Client())->get_enrollments_by_email($email);
                if (is_wp_error($result)) {
                    $this->redirect_notice('error', $result->get_error_message(), 'lui-data');
                }
                set_transient('lui_last_enrollments', $result, 10 * MINUTE_IN_SECONDS);
                $this->redirect_notice('success', __('Enrollments fetched. See the response below.', 'learnupon-integration'), 'lui-data');
            }
            public function display_notice(): void
            {
                if (!current_user_can('manage_options')) {
                    return;
                }
                $notice = get_transient('lui_admin_notice_' . get_current_user_id());
                if (!$notice) {
                    return;
                }
                delete_transient('lui_admin_notice_' . get_current_user_id());
                printf('<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', esc_attr($notice['type']), esc_html($notice['message']));
            }
            private function authorize(string $nonce_action): void
            {
                if (!current_user_can('manage_options')) {
                    wp_die(esc_html__('You are not allowed to perform this action.', 'learnupon-integration'));
                }
                check_admin_referer($nonce_action);
            }
            private function redirect_notice(string $type, string $message, string $page = 'lui-settings'): void
            {
                set_transient('lui_admin_notice_' . get_current_user_id(), ['type' => $type, 'message' => $message], 30);
                wp_safe_redirect(admin_url('admin.php?page=' . $page));
                exit;
            }

            public function render_companies_page(): void
            {
                if (!current_user_can('manage_options')) {
                    wp_die(esc_html__('You are not allowed to manage companies.', 'learnupon-integration'));
                }

                $companies = new LUI_Company_Manager();
                $rows = $companies->all();
                $users = get_users([
                    'orderby' => 'display_name',
                    'order'   => 'ASC',
                    'fields'  => ['ID', 'display_name', 'user_email'],
                ]);

                echo '<div class="wrap"><h1>' . esc_html__('Company Management', 'learnupon-integration') . '</h1>';

                if (isset($_GET['lui_notice'])) {
                    $notice = sanitize_key(wp_unslash($_GET['lui_notice']));
                    $messages = [
                        'created'  => __('Company created successfully.', 'learnupon-integration'),
                        'assigned' => __('Group Manager assigned successfully.', 'learnupon-integration'),
                        'error'    => __('The operation failed. Please check the submitted values.', 'learnupon-integration'),
                    ];

                    if (isset($messages[$notice])) {
                        $class = $notice === 'error' ? 'notice-error' : 'notice-success';
                        echo '<div class="notice ' . esc_attr($class) . ' is-dismissible"><p>'
                            . esc_html($messages[$notice]) . '</p></div>';
                    }
                }

                echo '<h2>' . esc_html__('Create Company', 'learnupon-integration') . '</h2>';
                echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
                echo '<input type="hidden" name="action" value="lui_create_company">';
                wp_nonce_field('lui_create_company');
                echo '<p><label for="lui-company-name">'
                    . esc_html__('Company name', 'learnupon-integration') . '</label><br>';
                echo '<input id="lui-company-name" name="company_name" type="text" class="regular-text" required></p>';
                echo '<p><button class="button button-primary">'
                    . esc_html__('Create Company', 'learnupon-integration') . '</button></p>';
                echo '</form>';

                echo '<hr><h2>' . esc_html__('Companies and Manager Assignment', 'learnupon-integration') . '</h2>';

                echo '<table class="widefat striped"><thead><tr>';
                echo '<th>' . esc_html__('Company', 'learnupon-integration') . '</th>';
                echo '<th>' . esc_html__('Company ID', 'learnupon-integration') . '</th>';
                echo '<th>' . esc_html__('Current Manager', 'learnupon-integration') . '</th>';
                echo '<th>' . esc_html__('Assign Manager', 'learnupon-integration') . '</th>';
                echo '</tr></thead><tbody>';

                if (!$rows) {
                    echo '<tr><td colspan="4">' . esc_html__('No companies found.', 'learnupon-integration') . '</td></tr>';
                }

                foreach ($rows as $company) {
                    $manager = $company->manager_user_id
                        ? get_user_by('id', (int) $company->manager_user_id)
                        : false;

                    echo '<tr><td>' . esc_html($company->company_name) . '</td>';
                    echo '<td>' . esc_html((string) $company->id) . '</td>';
                    echo '<td>' . esc_html($manager ? $manager->display_name . ' (' . $manager->user_email . ')' : 'Not assigned') . '</td>';
                    echo '<td><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
                    echo '<input type="hidden" name="action" value="lui_assign_company_manager">';
                    echo '<input type="hidden" name="head_manager_user_id" value="' . esc_attr((string) $company->id) . '">';
                    wp_nonce_field('lui_assign_company_manager_' . (int) $company->id);

                    echo '<select name="manager_user_id" required>';
                    echo '<option value="">' . esc_html__('Select a WordPress user', 'learnupon-integration') . '</option>';

                    foreach ($users as $user) {
                        printf(
                            '<option value="%1$d" %2$s>%3$s (%4$s)</option>',
                            (int) $user->ID,
                            selected((int) $company->manager_user_id, (int) $user->ID, false),
                            esc_html($user->display_name),
                            esc_html($user->user_email)
                        );
                    }

                    echo '</select> ';
                    echo '<button class="button">' . esc_html__('Assign', 'learnupon-integration') . '</button>';
                    echo '</form></td></tr>';
                }

                echo '</tbody></table></div>';
            }
        }
