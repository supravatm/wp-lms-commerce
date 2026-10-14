<?php
defined('ABSPATH') || exit;

class LUI_Group_Manager
{
    private LUI_Company_Manager $companies;


    public function __construct(LUI_Company_Manager $companies)
    {
        $this->companies = $companies;
        add_action('init', [$this, 'register_role']);
    }

    public function register_role(): void
    {
        $roles = [
            'group_manager' => [
                'name' => __('Group Manager', 'learnupon-integration'),
                'capabilities' => [
                    'read' => true,
                    'lui_manage_group_members' => true,
                    'lui_assign_group_courses' => true,
                ],
            ],
            'group_head_manager' => [
                'name' => __('Group Head Manager', 'learnupon-integration'),
                'capabilities' => [
                    'read' => true,
                    'lui_manage_group_managers' => true,
                    'lui_manage_group_members' => true,
                    'lui_allocate_group_seats' => true,
                    'lui_assign_group_courses' => true,
                ],
            ],
        ];

        foreach ($roles as $role_slug => $role_data) {
            $role = get_role($role_slug);

            if (!$role) {
                add_role(
                    $role_slug,
                    $role_data['name'],
                    $role_data['capabilities']
                );

                continue;
            }

            // Update capabilities for an existing role.
            foreach ($role_data['capabilities'] as $capability => $grant) {
                $role->add_cap($capability, $grant);
            }
        }
    }

    public function invite_member(int $group_manager_user_id, string $email, int $order_id, int $order_item_id, string $first_name = '', string $last_name = ''): int|WP_Error
    {
        error_log(wp_json_encode([$group_manager_user_id, $email, $first_name, $last_name], JSON_PRETTY_PRINT));
        $email = sanitize_email($email);
        if (!is_email($email)) {
            return new WP_Error('lui_invalid_email', __('Enter a valid email address.', 'learnupon-integration'));
        }
        $existing = get_user_by('email', $email);
        if ($existing) {
            $added = $this->companies->add_member($group_manager_user_id, (int) $existing->ID, $order_id, $order_item_id);
            return is_wp_error($added) ? $added : (int) $existing->ID;
        }

        $username = sanitize_user(current(explode('@', $email)), true);
        if ($username === '') {
            $username = 'learner';
        }
        $candidate = $username;
        for ($i = 1; username_exists($candidate); $i++) {
            $candidate = $username . $i;
        }

        $password = wp_generate_password(24, true, true);
        $user_id = wp_insert_user([
            'user_login' => $candidate,
            'user_email' => $email,
            'user_pass' => $password,
            'first_name' => sanitize_text_field($first_name),
            'last_name' => sanitize_text_field($last_name),
            'role' => 'customer',
        ]);
        if (is_wp_error($user_id)) {
            return $user_id;
        }

        $added = $this->companies->add_member($group_manager_user_id, (int) $user_id, $order_id, $order_item_id);
        if (is_wp_error($added)) {
            require_once ABSPATH . 'wp-admin/includes/user.php';
            wp_delete_user((int) $user_id);
            return $added;
        }

        // Send the standard WordPress account setup/reset link; never email a generated password.
        // retrieve_password($candidate);
        return (int) $user_id;
    }


    public function groupManagerRelations()
    {
        return $this->companies->manager_relationships();
    }
    /**
     * Invite a Group Manager and assign an available course allocation.
     *
     * Validates that the current user is the specified Group Head Manager,
     * verifies the email address and course allocation, creates or updates
     * the Group Manager account, establishes the manager relationship, and
     * assigns the selected course allocation to the Group Manager.
     *
     * Sends WooCommerce's New Account email when a new WordPress user
     * account is created successfully.
     *
     * @param int    $head_manager_user_id ID of the Group Head Manager.
     * @param string $email                Email address of the Group Manager.
     * @param string $first_name           Group Manager's first name.
     * @param string $last_name            Group Manager's last name.
     * @param int    $allocation_id        ID of the course seat allocation.
     *
     * @return int|WP_Error Group Manager user ID on success, or WP_Error
     *                      if validation, account creation, relationship
     *                      creation, or allocation assignment fails.
     */
    public function invite_group_manager(
        int $head_manager_user_id,
        string $email,
        string $first_name = '',
        string $last_name = '',
        int $allocation_id = 0,
        int $order_id = 0
    ): int|WP_Error {
        $current_user = wp_get_current_user();
        // error_log('Allocation SQL Query: ' . $head_manager_user_id "");
        error_log(wp_json_encode([$head_manager_user_id, $email, $first_name, $last_name, $allocation_id, $order_id], JSON_PRETTY_PRINT));
        if (
            (int) $current_user->ID !== $head_manager_user_id ||
            ! in_array('group_head_manager', (array) $current_user->roles, true)
        ) {
            error_log('Only the Group Head Manager can invite group managers.');
            return new WP_Error(
                'lui_forbidden',
                __('Only the Group Head Manager can invite group managers.', 'learnupon-integration')
            );
        }

        $email = sanitize_email($email);

        if (! is_email($email)) {
            error_log('Enter a valid email address.');
            return new WP_Error(
                'lui_invalid_email',
                __('Enter a valid email address.', 'learnupon-integration')
            );
        }

        if ($allocation_id <= 0) {
            error_log('Select a valid course allocation.');
            return new WP_Error(
                'lui_invalid_allocation',
                __('Select a valid course allocation.', 'learnupon-integration')
            );
        }

        global $wpdb;

        $table = $wpdb->prefix . 'lui_seat_allocations';

        // Validate ownership, status, assignment and seat availability.
        $allocation = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id
             FROM {$table}
             WHERE order_item_id = %d
               AND head_manager_user_id = %d
               AND status = %s
               AND order_id = %d
               AND (group_manager_user_id IS NULL
                    OR group_manager_user_id = 0)
               AND (purchased_seats - reserved_seats - assigned_seats) > 0
             LIMIT 1",
                $allocation_id,
                $head_manager_user_id,
                'active',
                $order_id
            )
        );
        error_log('Allocation SQL Query: ' . preg_replace('/\s+/', ' ', trim($wpdb->last_query)));

        if (! $allocation) {
            error_log('This course allocation is unavailable or has no seats remaining.');
            return new WP_Error(
                'lui_allocation_unavailable',
                __('This course allocation is unavailable or has no seats remaining.', 'learnupon-integration')
            );
        }

        $existing   = get_user_by('email', $email);
        $is_new_user = false;

        if ($existing) {
            $user_id = (int) $existing->ID;

            // Do not allow an existing Head Manager to become a Group Manager.
            $user = new WP_User($user_id);

            if (in_array('group_head_manager', (array) $user->roles, true)) {
                error_log('This user already has the Group Manager role.');
                return new WP_Error(
                    'lui_invalid_manager_role',
                    __('This user already has the Group Manager role.', 'learnupon-integration')
                );
            }
            $user->add_role('group_manager');
        } else {
            $username = sanitize_user(
                (string) current(explode('@', $email)),
                true
            );

            if ($username === '') {
                $username = 'groupmanager';
            }

            $base_username = $username;
            $suffix = 1;

            while (username_exists($username)) {
                $username = $base_username . $suffix++;
            }

            $user_id = wp_insert_user([
                'user_login' => $username,
                'user_email' => $email,
                'user_pass'  => wp_generate_password(24, true, true),
                'first_name' => sanitize_text_field($first_name),
                'last_name'  => sanitize_text_field($last_name),
                'role'       => 'group_manager',
            ]);

            if (is_wp_error($user_id)) {
                error_log(
                    'Group Manager creation failed: ' .
                        $user_id->get_error_message()
                );

                return $user_id;
            }

            $user_id = (int) $user_id;
            $is_new_user = true;
        }

        // Create the Head Manager–Group Manager relationship.
        $added = $this->companies->add_group_manager(
            $head_manager_user_id,
            $user_id
        );

        if (is_wp_error($added)) {
            if ($is_new_user) {
                wp_delete_user($user_id);
            }

            return $added;
        }

        // Assign the course allocation to this Group Manager.
        // Conditional update prevents assigning an already-claimed allocation.
        $updated = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table}
             SET group_manager_user_id = %d
             WHERE order_item_id = %d
                AND order_id = %d
               AND head_manager_user_id = %d
               AND status = %s
               AND (group_manager_user_id IS NULL
                    OR group_manager_user_id = 0)
               AND (purchased_seats - reserved_seats - assigned_seats) > 0",
                $user_id,
                $allocation_id,
                $order_id,
                $head_manager_user_id,
                'active'
            )
        );
        error_log((string) $wpdb->last_query);
        if (1 !== $updated) {
            error_log('The Group Manager could not be assigned to this course allocation.');
            return new WP_Error(
                'lui_allocation_assignment_failed',
                __('The Group Manager could not be assigned to this course allocation.', 'learnupon-integration')
            );
        }

        // Send WooCommerce's New Account email only for newly created users.
        if ($is_new_user && function_exists('WC')) {
            $mailer = WC()->mailer();
            $emails = $mailer->get_emails();

            if (isset($emails['WC_Email_Customer_New_Account'])) {
                $emails['WC_Email_Customer_New_Account']->trigger(
                    $user_id,
                    '',
                    true
                );
            }
        }
        return $user_id;
    }
}
