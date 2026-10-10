<?php
defined('ABSPATH') || exit;

class LUI_Enrollment_Manager
{
    private LUI_Company_Manager $companies;
    private LUI_Seat_Allocation $seats;
    private object $api;
    private object $users;

    public function __construct(LUI_Company_Manager $companies, LUI_Seat_Allocation $seats, object $api_client, object $user_sync)
    {
        $this->companies = $companies;
        $this->seats = $seats;
        $this->api = $api_client;
        $this->users = $user_sync;
    }

    public function assign_course(int $head_manager_user_id, int $wp_user_id, string $course_id): bool|WP_Error
    {
        if (!$this->can_assign($head_manager_user_id)) {
            return new WP_Error('lui_forbidden', __('You cannot assign courses for this company.', 'learnupon-integration'));
        }
        $user = get_user_by('id', $wp_user_id);
        $course_id = sanitize_text_field($course_id);
        if (!$user || $course_id === '') {
            return new WP_Error('lui_invalid_assignment', __('A valid member and course are required.', 'learnupon-integration'));
        }

        // Ensure the learner belongs to this company.
        $members = $this->companies->members($head_manager_user_id, $group_manager_user_id);

        $member_ids = array_map(static fn($m) => (int) $m->user_id, $members);
        if (!in_array($wp_user_id, $member_ids, true)) {
            return new WP_Error('lui_not_group_member', __('The learner is not a member of this company.', 'learnupon-integration'));
        }

        $reserved = $this->seats->reserve(
            $head_manager_user_id,
            $group_manager_user_id,
            $course_id,
            1
        );

        if (is_wp_error($reserved)) return $reserved;

        $synced = $this->sync_user($wp_user_id);
        if (is_wp_error($synced)) {
            $this->seats->release(
                $head_manager_user_id,
                $group_manager_user_id,
                $course_id,
                1
            );
            return $synced;
        }

        $email = $user->user_email;
        $result = $this->create_remote_enrollment($email, $course_id);
        if (is_wp_error($result)) {
            $this->seats->release(
                $head_manager_user_id,
                $group_manager_user_id,
                $course_id,
                1
            );
            return $result;
        }

        if (!$this->seats->confirm(
            $head_manager_user_id,
            $group_manager_user_id,
            $course_id,
            1
        )) {
            return new WP_Error('lui_seat_confirmation_failed', __('Enrollment succeeded, but seat confirmation failed. Reconcile this assignment before retrying.', 'learnupon-integration'));
        }

        do_action('lui_course_enrollment_created', $head_manager_user_id, $wp_user_id, $course_id, $result);
        return true;
    }

    private function sync_user(int $wp_user_id): mixed
    {
        foreach (['sync_user', 'sync_customer', 'sync'] as $method) {
            if (is_callable([$this->users, $method])) {
                $result = $this->users->{$method}($wp_user_id);
                if (is_wp_error($result) || $result === false) {
                    return is_wp_error($result) ? $result : new WP_Error('lui_user_sync_failed', __('LearnUpon user synchronization failed.', 'learnupon-integration'));
                }
                return $result;
            }
        }
        return new WP_Error('lui_user_sync_method_missing', __('No supported user-sync method was found. Adapt LUI_Enrollment_Manager::sync_user() to your existing class-lui-user-sync.php API.', 'learnupon-integration'));
    }

    private function create_remote_enrollment(string $email, string $course_id): mixed
    {
        foreach (['create_enrollment', 'enroll_user', 'create_course_enrollment'] as $method) {
            if (is_callable([$this->api, $method])) {
                $result = $this->api->{$method}($email, $course_id);
                if (is_wp_error($result) || $result === false) {
                    return is_wp_error($result) ? $result : new WP_Error('lui_enrollment_failed', __('LearnUpon enrollment failed.', 'learnupon-integration'));
                }
                return $result;
            }
        }
        return new WP_Error('lui_api_method_missing', __('No supported enrollment method was found. Add an adapter for your existing class-lui-api-client.php.', 'learnupon-integration'));
    }

    private function can_assign(int $head_manager_user_id): bool
    {
        if (current_user_can('manage_options')) return true;
        return current_user_can('lui_assign_group_courses')
            && $this->companies->manager_owns($head_manager_user_id, get_current_user_id());
    }
}
