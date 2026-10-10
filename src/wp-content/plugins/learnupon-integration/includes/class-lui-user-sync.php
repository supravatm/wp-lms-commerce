<?php
if (!defined('ABSPATH')) {
    exit;
}

final class LUI_User_Sync
{
    private LUI_API_Client $api;
    public function __construct(?LUI_API_Client $api = null)
    {
        $this->api = $api ?? new LUI_API_Client();
    }

    public function sync(int $user_id)
    {
        $wp_user = get_user_by('id', $user_id);
        if (!$wp_user instanceof WP_User) {
            return new WP_Error('lui_user_missing', __('WordPress user not found.', 'learnupon-integration'));
        }
        $first = (string) get_user_meta($user_id, 'first_name', true);
        $last = (string) get_user_meta($user_id, 'last_name', true);
        $existing_id = (int) get_user_meta($user_id, '_lui_learnupon_user_id', true);
        $payload = [
            'email' => $wp_user->user_email,
            'first_name' => $first,
            'last_name' => $last,
            'enabled' => true,
            'language' => 'en',
        ];
        if ($existing_id > 0) {
            $payload['id'] = $existing_id;
            $result = $this->api->update_user($payload);
        } else {
            // LearnUpon requires a password on user creation. Generate a random one; never reuse the WordPress password.
            $payload['password'] = wp_generate_password(32, true, true);
            $result = $this->api->create_user($payload);
        }
        if (is_wp_error($result)) {
            return $result;
        }
        $learnupon_user = $this->extract_user($result);
        if (!empty($learnupon_user['id'])) {
            update_user_meta($user_id, '_lui_learnupon_user_id', absint($learnupon_user['id']));
        } elseif (!$existing_id) {
            // Do not guess the ID from an unknown response shape; search by email on the next attempt.
            LUI_Logger::log('warning', 'LearnUpon user created/updated but response contained no recognizable user ID.', ['wp_user_id' => $user_id]);
        }
        update_user_meta($user_id, '_lui_last_user_sync', current_time('mysql', true));
        LUI_Logger::log('info', 'WordPress user synced to LearnUpon.', ['wp_user_id' => $user_id]);
        return $result;
    }

    private function extract_user(array $result): array
    {
        foreach (['User', 'user', 'users'] as $key) {
            if (isset($result[$key]) && is_array($result[$key])) {
                if (isset($result[$key]['id'])) {
                    return $result[$key];
                }
                if (isset($result[$key][0]) && is_array($result[$key][0])) {
                    return $result[$key][0];
                }
            }
        }
        return isset($result['id']) ? $result : [];
    }
}
