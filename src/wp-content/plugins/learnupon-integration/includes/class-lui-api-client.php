<?php
if (!defined('ABSPATH')) {
    exit;
}

final class LUI_API_Client
{
    private string $base_url;
    private string $username;
    private string $password;
    private int $timeout;

    public function __construct(?array $settings = null)
    {
        $settings = $settings ?? get_option('lui_settings', []);
        $portal = isset($settings['portal_url']) ? trim((string) $settings['portal_url']) : '';
        $this->base_url = untrailingslashit(esc_url_raw($portal));
        $this->username = isset($settings['api_username']) ? trim((string) $settings['api_username']) : '';
        $this->password = isset($settings['api_password']) ? (string) $settings['api_password'] : '';
        $this->timeout = 20;
    }

    public function is_configured(): bool
    {
        return $this->base_url !== '' && $this->username !== '' && $this->password !== '' && wp_parse_url($this->base_url, PHP_URL_SCHEME) === 'https';
    }

    public function request(string $method, string $path, array $query = [], ?array $body = null)
    {
        if (!$this->is_configured()) {
            return new WP_Error('lui_not_configured', __('Configure an HTTPS LearnUpon portal URL and API credentials first.', 'learnupon-integration'));
        }
        $path = ltrim($path, '/');
        $url = $this->base_url . '/api/v1/' . $path;
        if ($query) {
            $url = add_query_arg($query, $url);
        }
        $args = [
            'method' => strtoupper($method),
            'timeout' => $this->timeout,
            'redirection' => 0,
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode($this->username . ':' . $this->password),
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
        ];
        if ($body !== null) {
            $args['body'] = wp_json_encode($body);
        }
        $response = wp_remote_request($url, $args);
        if (is_wp_error($response)) {
            LUI_Logger::log('error', 'LearnUpon request failed at transport level.', ['path' => $path, 'error' => $response->get_error_code()]);
            return new WP_Error('lui_transport_error', __('Could not connect to LearnUpon. Check the portal URL and server connectivity.', 'learnupon-integration'));
        }
        $status = (int) wp_remote_retrieve_response_code($response);
        $raw = (string) wp_remote_retrieve_body($response);
        $data = $raw !== '' ? json_decode($raw, true) : [];
        if ($status < 200 || $status >= 300) {
            LUI_Logger::log('error', 'LearnUpon returned an unsuccessful HTTP status.', ['path' => $path, 'status' => $status]);
            $message = $status === 401 ? __('LearnUpon rejected the API credentials.', 'learnupon-integration') : sprintf(__('LearnUpon returned HTTP %d. See logs for details.', 'learnupon-integration'), $status);
            return new WP_Error('lui_http_error', $message, ['status' => $status]);
        }
        if ($raw !== '' && !is_array($data)) {
            return new WP_Error('lui_invalid_json', __('LearnUpon returned a response that was not valid JSON.', 'learnupon-integration'));
        }
        return $data;
    }

    public function test_connection()
    {
        return $this->request('GET', 'courses', ['page' => 1]);
    }
    public function get_courses(int $page = 1)
    {
        return $this->request('GET', 'courses', ['page' => max(1, $page)]);
    }
    public function get_user_by_email(string $email)
    {
        return $this->request('GET', 'users/search', ['email' => $email]);
    }
    public function create_user(array $user)
    {
        return $this->request('POST', 'users', ['User' => $user]);
    }
    public function update_user(array $user)
    {
        return $this->request('PUT', 'users', ['User' => $user]);
    }
    public function get_enrollments_by_email(string $email)
    {
        return $this->request('GET', 'enrollments/search', ['email' => $email]);
    }
    public function enroll_user(array $enrollment)
    {
        return $this->request('POST', 'enrollments', ['Enrollment' => $enrollment]);
    }
}
