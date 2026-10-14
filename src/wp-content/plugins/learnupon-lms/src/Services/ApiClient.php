<?php

declare(strict_types=1);

namespace LearnUponLMS\Services;

final class ApiClient
{
    private string $baseUrl;
    private string $username;
    private string $password;
    private int $timeout = 20;

    public function __construct(?array $settings = null)
    {
        $settings = $settings ?? get_option('learnupon_lms_settings', []);

        $portal = isset($settings['portal_url']) ? trim((string) $settings['portal_url']) : '';

        $this->baseUrl = untrailingslashit(esc_url_raw($portal));
        $this->username = trim((string) ($settings['api_username'] ?? ''));
        $this->password = (string) ($settings['api_password'] ?? '');
    }

    public function testConnection(): array|\WP_Error
    {
        error_log("Test Connection Calling API");
        return $this->request('GET', 'courses', ['page' => 1]);
    }
    public function getCourses(int $page = 1)
    {
        return $this->request('GET', 'courses', ['page' => max(1, $page)]);
    }
    public function getUsers()
    {
        return $this->request('GET', 'users');
    }
    public function get_user_by_id(int $id)
    {
        return $this->request('GET', 'users/' . $id);
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
    private function request(
        string $method,
        string $path,
        array $query = []
    ): array|\WP_Error {
        if (
            $this->baseUrl === ''
            || $this->username === ''
            || $this->password === ''
            || wp_parse_url($this->baseUrl, PHP_URL_SCHEME) !== 'https'
        ) {
            return new \WP_Error(
                'learnupon_not_configured',
                __('Configure an HTTPS LearnUpon portal URL and API credentials first.', 'learnupon-lms')
            );
        }

        $url = $this->baseUrl . '/api/v1/' . ltrim($path, '/');

        if ($query !== []) {
            $url = add_query_arg($query, $url);
        }

        $response = wp_remote_request($url, [
            'method' => strtoupper($method),
            'timeout' => $this->timeout,
            'redirection' => 0,
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode(
                    $this->username . ':' . $this->password
                ),
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
        ]);
        // error_log(print_r($response, true));

        if (is_wp_error($response)) {
            return new \WP_Error(
                'learnupon_transport_error',
                __('Could not connect to LearnUpon. Check the portal URL and server connectivity.', 'learnupon-lms')
            );
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        $body = (string) wp_remote_retrieve_body($response);

        if ($status < 200 || $status >= 300) {
            $message = $status === 401
                ? __('LearnUpon rejected the API credentials.', 'learnupon-lms')
                : sprintf(
                    __('LearnUpon returned HTTP %d.', 'learnupon-lms'),
                    $status
                );
            return new \WP_Error(
                'learnupon_http_error',
                $message,
                ['status' => $status]
            );
        }
        $data = $body !== '' ? json_decode($body, true) : [];
        if ($body !== '' && !is_array($data)) {
            return new \WP_Error(
                'learnupon_invalid_json',
                __('LearnUpon returned invalid JSON.', 'learnupon-lms')
            );
        }
        return $data;
    }
}
