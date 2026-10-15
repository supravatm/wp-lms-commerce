<?php

declare(strict_types=1);

namespace LearnUponLMS\Services;

use LearnUponLMS\Core\Roles;

use WP_Error;

final class UserSync
{
    public function sync(array $user): int|WP_Error
    {
        $userId = absint($user['id'] ?? 0);
        if (!$userId) {
            return new WP_Error(
                'learnupon_lms_invalid_user',
                'User ID is required.'
            );
        }
        $userId = $this->createUser($user);
        return $userId;
    }


    /**
     * Create or update a WordPress user using LearnUpon user data.
     *
     * @param array{
     *     id: int|string,
     *     email: string,
     *     first_name?: string,
     *     last_name?: string,
     *     role?: string
     * } $user_data LearnUpon user data.
     *
     * @return int|\WP_Error WordPress user ID on success, WP_Error on failure.
     */
    function createUser(array $user_data): int|\WP_Error
    {
        $email = sanitize_email($user_data['email'] ?? '');
        $learnupon_user_id = absint($user_data['id'] ?? 0);

        if (!is_email($email)) {
            return new \WP_Error(
                'learnupon_lms_invalid_email',
                __('Please provide a valid email address.', 'learnupon-lms')
            );
        }

        if (!$learnupon_user_id) {
            return new \WP_Error(
                'learnupon_lms_invalid_learnupon_user_id',
                __('A valid LearnUpon user ID is required.', 'learnupon-lms')
            );
        }
        $role = sanitize_key($user_data['user_type'] ?? Roles::LEARNER);
        $allowed_roles = array_keys(Roles::all());

        if (!in_array($role, $allowed_roles, true)) {
            return new \WP_Error(
                'learnupon_lms_invalid_role',
                __('The requested user role is not allowed.', 'learnupon-lms')
            );
        }

        $existing_user_id = email_exists($email);

        $userdata = [
            'user_email' => $email,
            'first_name' => sanitize_text_field($user_data['first_name'] ?? ''),
            'last_name'  => sanitize_text_field($user_data['last_name'] ?? ''),
        ];

        if ($existing_user_id) {
            // Never touch privileged accounts; only record the LearnUpon mapping.
            $existing_user = get_userdata($existing_user_id);
            $protected = ['administrator', 'shop_manager'];

            if ($existing_user && array_intersect($protected, (array) $existing_user->roles)) {
                update_user_meta($existing_user_id, 'learnupon_lms_user_id', $learnupon_user_id);

                return (int) $existing_user_id;
            }

            // Update profile fields without replacing the user's current roles.
            $userdata['ID'] = $existing_user_id;
            $user_id = wp_update_user($userdata);

            if (!is_wp_error($user_id) && $existing_user && !in_array($role, (array) $existing_user->roles, true)) {
                $existing_user->add_role($role);
            }
        } else {
            // Create a new WordPress user.
            $userdata['role'] = $role;
            $userdata['user_login'] = $email;
            $userdata['user_pass'] = wp_generate_password(32, true, true);

            $user_id = wp_insert_user($userdata);
        }

        if (is_wp_error($user_id)) {
            return $user_id;
        }
        // Store the corresponding LearnUpon user ID.
        update_user_meta(
            $user_id,
            'learnupon_lms_user_id',
            $learnupon_user_id
        );
        return (int) $user_id;
    }
}
