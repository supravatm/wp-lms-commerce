<?php
if (!defined('ABSPATH')) {
    exit;
}

final class LUI_Plugin
{
    private static ?self $instance = null;
    private ?LUI_Group_Manager $group_manager = null;
    private ?LUI_Enrollment_Manager $enrollment_manager;
    public static function instance(): self

    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    public static function activate(): void
    {
        if (!get_option('lui_settings')) {
            add_option('lui_settings', ['portal_url' => '', 'api_username' => '', 'api_password' => ''], '', false);
        }
        self::install_extensions();
    }
    public static function deactivate(): void
    {
        wp_clear_scheduled_hook('lui_process_queue');
    }
    public function init(): void
    {
        $companies = new LUI_Company_Manager();
        $seats     = new LUI_Seat_Allocation();
        $api       = new LUI_API_Client();
        $users     = new LUI_User_Sync($api);

        $this->group_manager = new LUI_Group_Manager($companies);

        $this->enrollment_manager = new LUI_Enrollment_Manager(
            $companies,
            $seats,
            $api,
            $users
        );

        new LUI_Manager_Dashboard(
            $this->group_manager,
            $this->enrollment_manager
        );



        (new LUI_Admin())->init();
        add_action('lui_process_queue', [LUI_Queue::class, 'process']);

        (new LUI_Bulk_Order_Admin())->init();

        if (class_exists('WooCommerce')) {
            (new LUI_WooCommerce())->init();
        }

        add_action('user_register', [$this, 'queue_new_user'], 20, 1);
        add_action('profile_update', [$this, 'queue_updated_user'], 20, 1);
        add_action('show_user_profile', [$this, 'show_user_mapping']);
        add_action('edit_user_profile', [$this, 'show_user_mapping']);
        add_action('admin_notices', [$this, 'dependency_notice']);
    }
    public function queue_new_user(int $user_id): void
    {
        if (get_option('lui_settings', [])['portal_url'] ?? false) {
            LUI_Queue::enqueue_user_sync($user_id);
        }
    }
    public function queue_updated_user(int $user_id): void
    {
        if (get_option('lui_settings', [])['portal_url'] ?? false) {
            LUI_Queue::enqueue_user_sync($user_id);
        }
    }
    public function show_user_mapping(WP_User $user): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        $id = get_user_meta($user->ID, '_lui_learnupon_user_id', true);
        $last = get_user_meta($user->ID, '_lui_last_user_sync', true);
        echo '<h2>LearnUpon Integration</h2><table class="form-table"><tr><th>LearnUpon user ID</th><td>' . esc_html($id ? (string) $id : 'Not mapped') . '</td></tr><tr><th>Last sync (UTC)</th><td>' . esc_html($last ? (string) $last : 'Never') . '</td></tr></table>';
    }
    public function dependency_notice(): void
    {
        if (!current_user_can('manage_options') || class_exists('WooCommerce')) {
            return;
        }
        echo '<div class="notice notice-warning"><p>' . esc_html__('LearnUpon Integration: WooCommerce is not active. API settings and user/course tools remain available, but order integration is disabled.', 'learnupon-integration') . '</p></div>';
    }

    public static function install_extensions(): void
    {
        $companies = new LUI_Company_Manager();
        $companies->install();

        $seats = new LUI_Seat_Allocation();
        $seats->install();
    }
}
