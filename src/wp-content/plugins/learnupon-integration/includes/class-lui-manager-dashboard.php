<?php

defined('ABSPATH') || exit;

class LUI_Manager_Dashboard
{
    private LUI_Group_Manager $groups;

    public function __construct(LUI_Group_Manager $groups)
    {
        $this->groups = $groups;

        add_shortcode(
            'lui_manager_dashboard',
            [$this, 'render']
        );

        add_action(
            'init',
            [$this, 'handle_post']
        );
    }

    /**
     * Handle dashboard form submissions.
     */
    public function handle_post(): void
    {
        if (
            ! is_user_logged_in()
            || 'POST' !== ($_SERVER['REQUEST_METHOD'] ?? '')
            || empty($_POST['lui_action'])
        ) {
            return;
        }

        $action = sanitize_key(
            wp_unslash($_POST['lui_action'])
        );



        if ('invite_group_manager' === $action) {
            if (! current_user_can('lui_manage_group_managers')) {
                wp_die(
                    esc_html__('Permission denied.', 'learnupon-integration'),
                    '',
                    ['response' => 403]
                );
            }

            $nonce = sanitize_text_field(
                wp_unslash($_POST['_wpnonce'] ?? '')
            );

            if (! wp_verify_nonce($nonce, 'lui_manager_dashboard')) {
                wp_die(
                    esc_html__('Security check failed.', 'learnupon-integration'),
                    '',
                    ['response' => 403]
                );
            }
            $result = $this->groups->invite_group_manager(
                get_current_user_id(),
                sanitize_email(wp_unslash($_POST['email'] ?? '')),
                sanitize_text_field(wp_unslash($_POST['first_name'] ?? '')),
                sanitize_text_field(wp_unslash($_POST['last_name'] ?? '')),
                absint(wp_unslash($_POST['allocation_id'] ?? 0)),
                absint(wp_unslash($_POST['order_id'] ?? 0))
            );
        }

        if ('invite_member' === $action) {
            if (! current_user_can('lui_manage_group_members')) {
                wp_die(esc_html__('Permission denied ff.', 'learnupon-integration'), '', ['response' => 403]);
            }
            $nonce = sanitize_text_field(wp_unslash($_POST['_wpnonce'] ?? ''));
            $result = $this->groups->invite_member(
                get_current_user_id(),
                sanitize_email(wp_unslash($_POST['email'] ?? '')),
                sanitize_text_field(wp_unslash($_POST['first_name'] ?? '')),
                sanitize_text_field(wp_unslash($_POST['last_name'] ?? '')),
                absint(wp_unslash($_POST['allocation_id'] ?? 0)),
                absint(wp_unslash($_POST['order_id'] ?? 0))
            );
        }
        $notice = is_wp_error($result) ? 'error' : 'success';
        // Return to the page containing the dashboard shortcode.
        $return_url = esc_url_raw(
            wp_unslash($_POST['lui_return_url'] ?? '')
        );
        if (empty($return_url)) {
            $return_url = wp_get_referer() ?: home_url('/');
        }
        // Prevent redirects to external URLs.
        $return_url = wp_validate_redirect($return_url, home_url('/'));
        $redirect_url = add_query_arg(
            'lui_notice',
            $notice,
            $return_url
        );
        wp_safe_redirect($redirect_url);
        exit;
    }

    /**
     * Render the correct dashboard for the current user.
     */
    public function render(): string
    {

        $user = wp_get_current_user();

        if (! is_user_logged_in()) {
            return '<p>' .
                esc_html__(
                    'Please log in to access your dashboard.',
                    'learnupon-integration'
                ) .
                '</p>';
        }

        $user = wp_get_current_user();

        if (in_array('group_head_manager', (array) $user->roles, true)) {
            return $this->render_head_manager_dashboard(
                (int) $user->ID
            );
        }

        if (in_array('group_manager', (array) $user->roles, true)) {
            return $this->render_group_manager_dashboard();
        }

        return '<p>' .
            esc_html__(
                'You do not have permission to access this dashboard.',
                'learnupon-integration'
            ) .
            '</p>';
    }

    /**
     * Render Group Manager dashboard.
     */
    private function render_group_manager_dashboard(): string
    {
        if (
            ! is_user_logged_in() || ! in_array('group_manager', wp_get_current_user()->roles, true)
        ) {
            return '<p>' . esc_html__(
                'You are not authorized to access this dashboard.',
                'learnupon-integration'
            ) . '</p>';
        }
        global $wpdb;
        $manager_id = get_current_user_id();
        $table      = $wpdb->prefix . 'lui_seat_allocations';
        $allocations = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, head_manager_user_id, order_id, order_item_id,
                    purchased_seats, reserved_seats, assigned_seats
             FROM {$table}
             WHERE group_manager_user_id = %d
               AND status = %s
             ORDER BY order_item_id ASC",
                $manager_id,
                'active'
            )
        );
        error_log('render_group_manager_dashboard allocation select SQL Query: ' . preg_replace('/\s+/', ' ', trim($wpdb->last_query)));
        ob_start(); ?>
        <div class="lui-manager-dashboard">
            <h3>
                <?php
                esc_html_e(
                    'Group Manager Dashboard',
                    'learnupon-integration'
                );
                ?>
            </h3>

            <p>
                <?php
                esc_html_e(
                    'Invite a learner and assign an available course seat.',
                    'learnupon-integration'
                );
                ?>
            </p>

            <form method="post">
                <input
                    type="hidden"
                    name="action"
                    value="lui_invite_member">
                <input type="hidden" name="lui_action" value="invite_member">
                <input type="hidden" name="order_id" id="lui_order_id" value="">

                <?php wp_nonce_field('lui_invite_member', 'lui_nonce'); ?>

                <p>
                    <label for="lui_first_name">
                        <?php esc_html_e('First name', 'learnupon-integration'); ?>
                    </label>
                    <input
                        type="text"
                        id="lui_first_name"
                        name="first_name"
                        required>
                </p>

                <p>
                    <label for="lui_last_name">
                        <?php esc_html_e('Last name', 'learnupon-integration'); ?>
                    </label>
                    <input
                        type="text"
                        id="lui_last_name"
                        name="last_name"
                        required>
                </p>

                <p>
                    <label for="lui_email">
                        <?php esc_html_e('Learner email', 'learnupon-integration'); ?>
                    </label>
                    <input
                        type="email"
                        id="lui_email"
                        name="email"
                        required>
                </p>

                <p>
                    <label for="lui_order_item_id">
                        <?php esc_html_e('Available course', 'learnupon-integration'); ?>
                    </label>

                    <select id="lui_order_item_id" name="order_item_id" required>
                        <option value="">
                            <?php esc_html_e('Select a course', 'learnupon-integration'); ?>
                        </option>

                        <?php foreach ($allocations as $allocation) : ?>
                            <?php
                            $available =
                                (int) $allocation->purchased_seats
                                - (int) $allocation->reserved_seats
                                - (int) $allocation->assigned_seats;
                            ?>

                            <?php if ($available > 0) : ?>
                                <option value="<?php echo esc_attr($allocation->order_item_id); ?>" data-order-id="<?php echo esc_attr($allocation->order_id); ?>">
                                    <?php
                                    echo esc_html(
                                        sprintf(
                                            'Course #%d — %d seats available',
                                            (int) $allocation->order_item_id,
                                            $available
                                        )
                                    );
                                    ?>
                                </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </p>

                <?php if (empty($allocations)) : ?>
                    <p>
                        <?php
                        esc_html_e(
                            'No course allocations were found for your account.',
                            'learnupon-integration'
                        );
                        ?>
                    </p>
                <?php endif; ?>

                <button type="submit">
                    <?php esc_html_e('Invite Learner', 'learnupon-integration'); ?>
                </button>
            </form>
        </div>



<?php return (string) ob_get_clean();
    }

    /**
     * Render Head Manager dashboard.
     */
    private function render_head_manager_dashboard(int $user_id): string
    {
        ob_start();

        echo '<div class="lui-manager-dashboard">';

        $this->render_notice();
        $this->render_group_manager_relationships();
        $this->render_invite_form();

        echo '</div>';

        return (string) ob_get_clean();
    }

    /**
     * Render a predefined notice.
     */
    private function render_notice(): void
    {
        $notice = sanitize_key(
            wp_unslash($_GET['lui_notice'] ?? '')
        );

        $messages = [
            'success' => [
                'class' => 'notice-success',
                'text'  => __(
                    'Group Manager invited successfully.',
                    'learnupon-integration'
                ),
            ],
            'error' => [
                'class' => 'notice-error',
                'text'  => __(
                    'Unable to invite the Group Manager. Please check the details and try again.',
                    'learnupon-integration'
                ),
            ],
        ];

        if (!isset($messages[$notice])) {
            return;
        }

        $message = $messages[$notice];

        printf(
            '<div class="notice %s is-dismissible" role="status"><p>%s</p></div>',
            esc_attr($message['class']),
            esc_html($message['text'])
        );
    }

    /**
     * Render Group Manager relationships.
     */
    private function render_group_manager_relationships(): void
    {
        $relationships = $this->groups->groupManagerRelations();

        echo '<h3>' .
            esc_html__(
                'Group Manager Assignments',
                'learnupon-integration'
            ) .
            '</h3>';

        if (empty($relationships)) {
            echo '<p>' .
                esc_html__(
                    'No Group Manager assignments found.',
                    'learnupon-integration'
                ) .
                '</p>';

            return;
        }

        echo '<table class="widefat striped">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('Group Manager', 'learnupon-integration') . '</th>';
        echo '<th>' . esc_html__('Email', 'learnupon-integration') . '</th>';
        echo '<th>' . esc_html__('Status', 'learnupon-integration') . '</th>';
        echo '</tr></thead><tbody>';

        foreach ($relationships as $relationship) {
            $manager_name = trim(
                ($relationship->manager_first_name ?? '') . ' ' .
                    ($relationship->manager_last_name ?? '')
            );

            echo '<tr>';
            echo '<td>' . esc_html($manager_name ?: '—') . '</td>';
            echo '<td>' . esc_html($relationship->manager_email ?? '—') . '</td>';
            echo '<td>' .
                esc_html(ucfirst($relationship->status ?? 'unknown')) .
                '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    /**
     * Render the invitation form.
     */
    private function render_invite_form(): void
    {
        if (! current_user_can('lui_manage_group_managers')) {
            return;
        }
        global $wpdb;
        $table = $wpdb->prefix . 'lui_seat_allocations';
        $allocations = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, order_id, order_item_id, purchased_seats,
                    reserved_seats, assigned_seats
             FROM {$table}
             WHERE head_manager_user_id = %d
               AND status = %s
               AND (group_manager_user_id IS NULL
                    OR group_manager_user_id = 0)
               AND (purchased_seats - reserved_seats - assigned_seats) > 0
             ORDER BY order_item_id ASC",
                get_current_user_id(),
                'active'
            )
        );
        error_log('SQL Query: ' . preg_replace('/\s+/', ' ', trim($wpdb->last_query)));
        echo '<h3>' .
            esc_html__(
                'Invite a Group Manager',
                'learnupon-integration'
            ) .
            '</h3>';

        if (empty($allocations)) {
            echo '<p>' .
                esc_html__(
                    'No unassigned course allocations with available seats were found.',
                    'learnupon-integration'
                ) .
                '</p>';

            return;
        }

        echo '<form method="post">';

        wp_nonce_field('lui_manager_dashboard');
        echo '<input type="hidden" name="lui_return_url" value="' .
            esc_url(get_permalink()) .
            '">';
        echo '<input type="hidden" name="lui_action" value="invite_group_manager">';

        // echo '<input type="hidden" name="order_id" id="lui_order_id" value="554">';
        echo '<input type="hidden" name="order_id" id="lui_order_id" value="">';
        echo '<p><label>' .
            esc_html__('Course allocation', 'learnupon-integration') .
            ' <select name="allocation_id" id="lui_allocation_id" required>';

        echo '<option value="">' .
            esc_html__('Select a course', 'learnupon-integration') .
            '</option>';

        foreach ($allocations as $allocation) {
            $available = (int) $allocation->purchased_seats
                - (int) $allocation->reserved_seats
                - (int) $allocation->assigned_seats;

            printf(
                '<option value="%1$d" data-order-id="%2$d">%3$s</option>',
                (int) $allocation->order_item_id,
                (int) $allocation->order_id,
                esc_html(
                    sprintf(
                        'Course #%1$d — Order #%2$d — %3$d seats available',
                        (int) $allocation->order_item_id,
                        (int) $allocation->order_id,
                        $available
                    )
                )
            );
        }

        echo '</select></label></p>';
        echo '<p><label>' .
            esc_html__('First name', 'learnupon-integration') .
            ' <input type="text" name="first_name"></label></p>';

        echo '<p><label>' .
            esc_html__('Last name', 'learnupon-integration') .
            ' <input type="text" name="last_name"></label></p>';
        echo '<p><label>' .
            esc_html__('Email', 'learnupon-integration') .
            ' <input type="email" name="email" required></label></p>';
        echo '<button type="submit">' .
            esc_html__('Invite Group Manager', 'learnupon-integration') .
            '</button>';

        echo '</form>';
        echo '<script>
(function () {
    const allocationSelect = document.getElementById("lui_allocation_id");
    const orderIdInput = document.getElementById("lui_order_id");

    if (!allocationSelect || !orderIdInput) {
        return;
    }

    allocationSelect.addEventListener("change", function () {
        const option = allocationSelect.options[allocationSelect.selectedIndex];
        orderIdInput.value = option.dataset.orderId || "";
    });
})();
</script>';
    }
}
