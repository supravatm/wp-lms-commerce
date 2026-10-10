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

    public function invite_member(int $head_manager_user_id, string $email, string $first_name = '', string $last_name = ''): int|WP_Error
    {
        $email = sanitize_email($email);
        if (!is_email($email)) {
            return new WP_Error('lui_invalid_email', __('Enter a valid email address.', 'learnupon-integration'));
        }
        $existing = get_user_by('email', $email);
        if ($existing) {
            $added = $this->companies->add_member($head_manager_user_id, (int) $existing->ID);
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

        $added = $this->companies->add_member($head_manager_user_id, (int) $user_id);
        if (is_wp_error($added)) {
            require_once ABSPATH . 'wp-admin/includes/user.php';
            wp_delete_user((int) $user_id);
            return $added;
        }

        // Send the standard WordPress account setup/reset link; never email a generated password.
        retrieve_password($candidate);
        return (int) $user_id;
    }


    public function groupManagerRelations()
    {
        return $this->companies->manager_relationships();
    }
    public function invite_group_manager(
        int $head_manager_user_id,
        string $email,
        string $first_name = '',
        string $last_name = ''
    ): int|WP_Error {

        // var_dump([$head_manager_user_id, $email, $first_name, $last_name]);

        $current_user = wp_get_current_user();

        // Only the logged-in Group Head Manager can invite managers.
        if (
            (int) $current_user->ID !== $head_manager_user_id ||
            !in_array('group_head_manager', (array) $current_user->roles, true)
        ) {
            return new WP_Error(
                'lui_forbidden',
                __('Only the Group Head Manager can invite group managers.', 'learnupon-integration')
            );
        }

        $email = sanitize_email($email);

        if (!is_email($email)) {
            return new WP_Error(
                'lui_invalid_email',
                __('Enter a valid email address.', 'learnupon-integration')
            );
        }

        // Check whether the user already exists.
        $existing = get_user_by('email', $email);
        // var_dump($existing);

        if ($existing) {
            $user_id = (int) $existing->ID;

            // Assign the Group Manager role.
            $user = new WP_User($user_id);
            $user->add_role('group_manager');
        } else {
            $username = sanitize_user(
                current(explode('@', $email)),
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
        }
        // Associate the Group Manager with the head manager's company.
        $added = $this->companies->add_group_manager($head_manager_user_id, $user_id);


        if (is_wp_error($added)) {
            error_log(
                'Associate the Group Manager with the head manager: ' .
                    $added->get_error_message()
            );
            return $added;
        }

        // Send the account setup/reset email.
        if (!function_exists('retrieve_password')) {
            require_once ABSPATH . WPINC . '/pluggable.php';
            require_once ABSPATH . 'wp-login.php';
        }

        retrieve_password(
            get_userdata($user_id)->user_login
        );

        return $user_id;
    }
}
