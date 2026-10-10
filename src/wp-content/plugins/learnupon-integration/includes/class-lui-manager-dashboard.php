<?php
defined('ABSPATH') || exit;

class LUI_Manager_Dashboard
{
    private LUI_Group_Manager $groups;

    public function __construct(
        LUI_Group_Manager $groups,
    ) {
        $this->groups = $groups;
        add_shortcode('lui_manager_dashboard', [$this, 'render']);
        add_action('init', [$this, 'handle_post']);
    }

    public function handle_post(): void
    {
        if (
            !is_user_logged_in() ||
            empty($_POST['lui_action'])
        ) {
            return;
        }

        $action = sanitize_key(wp_unslash($_POST['lui_action']));

        if ($action !== 'invite_group_manager') {
            return;
        }

        $nonce = sanitize_text_field(
            wp_unslash($_POST['_wpnonce'] ?? '')
        );

        if (!wp_verify_nonce($nonce, 'lui_manager_dashboard')) {
            wp_die(
                esc_html__('Security check failed.', 'learnupon-integration'),
                '',
                ['response' => 403]
            );
        }

        $head_manager_user_id = get_current_user_id();

        $result = $this->groups->invite_group_manager(
            $head_manager_user_id,
            sanitize_email(wp_unslash($_POST['email'] ?? '')),
            sanitize_text_field(wp_unslash($_POST['first_name'] ?? '')),
            sanitize_text_field(wp_unslash($_POST['last_name'] ?? ''))
        );

        $notice = is_wp_error($result)
            ? $result->get_error_message()
            : 'success';

        $redirect = add_query_arg(
            'lui_notice',
            $notice,
            wp_get_referer() ?: home_url('/')
        );

        wp_safe_redirect($redirect);
        exit;
    }

    public function render(): string
    {
        ob_start();
        if (!is_user_logged_in()) {
            return '<p>' .
                esc_html__('Please log in to manage your company.', 'learnupon-integration') .
                '</p>';
        }
        $user = wp_get_current_user();

        // Dashboard for Group Manager
        if (in_array('group_manager', (array) $user->roles, true)) {

            echo '<div class="lui-manager-dashboard">';
            return '<p>' .
                esc_html__('You are logged in your Group Manager account.', 'learnupon-integration') .
                '</p>';
            echo '</div>';
        }

        // Dashboard for Head Manager
        if (in_array('group_head_manager', (array) $user->roles, true)) {
            $user_id = (int) $user->ID;

            $orders = wc_get_orders([
                'customer_id' => $user_id,
                'limit'       => -1,
                'orderby'     => 'date',
                'order'       => 'DESC',
            ]);



            echo '<div class="lui-manager-dashboard">';

            if (isset($_GET['lui_notice'])) {
                $notice = sanitize_text_field(
                    wp_unslash($_GET['lui_notice'])
                );

                echo '<p role="status">';
                echo esc_html(
                    $notice === 'success'
                        ? __('Group Manager invited successfully.', 'learnupon-integration')
                        : $notice
                );
                echo '</p>';
            }

            echo '<h3>' . esc_html__('Courses', 'learnupon-integration') . '</h3>';

            if (empty($orders)) {
                echo '<p>' . esc_html__('No courses found.', 'learnupon-integration') . '</p>';
            } else {
                echo '<table class="widefat striped">';
                echo '<thead><tr>';
                echo '<th>' . esc_html__('Course Name', 'learnupon-integration') . '</th>';
                echo '<th>' . esc_html__('Seats', 'learnupon-integration') . '</th>';
                echo '</tr></thead><tbody>';

                foreach ($orders as $order) {
                    foreach ($order->get_items() as $item) {
                        echo '<tr>';
                        echo '<td>' . esc_html($item->get_name()) . '</td>';
                        echo '<td>' . esc_html((string) $item->get_quantity()) . '</td>';
                        echo '</tr>';
                    }
                }

                echo '</tbody></table>';
            }
            $relationships = $this->groups->groupManagerRelations();
            echo '<h3>' .
                esc_html__('Group Manager Assignments', 'learnupon-integration') .
                '</h3>';

            if (empty($relationships)) {
                echo '<p>' .
                    esc_html__('No Group Manager assignments found.', 'learnupon-integration') .
                    '</p>';;
            }

            echo '<table class="widefat striped">';
            echo '<thead><tr>';
            echo '<th>' . esc_html__('Head Group Manager', 'learnupon-integration') . '</th>';
            echo '<th>' . esc_html__('Head Manager Email', 'learnupon-integration') . '</th>';
            echo '<th>' . esc_html__('Group Manager', 'learnupon-integration') . '</th>';
            echo '<th>' . esc_html__('Group Manager Email', 'learnupon-integration') . '</th>';
            echo '<th>' . esc_html__('Status', 'learnupon-integration') . '</th>';
            echo '</tr></thead><tbody>';

            foreach ($relationships as $relationship) {
                $head_name = trim(
                    ($relationship->head_first_name ?? '') . ' ' .
                        ($relationship->head_last_name ?? '')
                );

                $manager_name = trim(
                    ($relationship->manager_first_name ?? '') . ' ' .
                        ($relationship->manager_last_name ?? '')
                );

                echo '<tr>';
                echo '<td>' . esc_html($head_name ?: '—') . '</td>';
                echo '<td>' . esc_html($relationship->head_email ?? '—') . '</td>';
                echo '<td>' . esc_html($manager_name ?: '—') . '</td>';
                echo '<td>' . esc_html($relationship->manager_email ?? '—') . '</td>';
                echo '<td>' . esc_html(ucfirst($relationship->status ?? 'unknown')) . '</td>';
                echo '</tr>';
            }

            echo '</tbody></table>';
            $head_name = trim(
                ($relationship->head_first_name ?? '') . ' ' .
                    ($relationship->head_last_name ?? '')
            );

            $manager_name = trim(
                ($relationship->manager_first_name ?? '') . ' ' .
                    ($relationship->manager_last_name ?? '')
            );

            echo '<h3>' . esc_html__('Invite a Group Manager', 'learnupon-integration') . '</h3>';
            echo '<form method="post">';

            wp_nonce_field('lui_manager_dashboard');

            echo '<input type="hidden" name="lui_action" value="invite_group_manager">';

            echo '<p><label>' . esc_html__('Email', 'learnupon-integration');
            echo ' <input type="email" name="email" required></label></p>';

            echo '<p><label>' . esc_html__('First name', 'learnupon-integration');
            echo ' <input type="text" name="first_name"></label></p>';

            echo '<p><label>' . esc_html__('Last name', 'learnupon-integration');
            echo ' <input type="text" name="last_name"></label></p>';

            echo '<button type="submit">';
            echo esc_html__('Invite Group Manager', 'learnupon-integration');
            echo '</button>';

            echo '</form>';
            echo '</div>';
        }
        return (string) ob_get_clean();
    }
}
