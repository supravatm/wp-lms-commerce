<?php
if (!defined('ABSPATH')) {
    exit;
}

final class LUI_Queue
{
    const OPTION = 'lui_retry_queue';
    public static function enqueue_user_sync(int $user_id, int $attempt = 0): void
    {
        $queue = get_option(self::OPTION, []);
        $key = 'user_sync_' . $user_id;
        $queue[$key] = ['type' => 'user_sync', 'user_id' => $user_id, 'attempt' => $attempt, 'run_at' => time() + min(3600, 60 * (2 ** min($attempt, 5)))];
        update_option(self::OPTION, $queue, false);
        if (!wp_next_scheduled('lui_process_queue')) {
            wp_schedule_single_event(time() + 60, 'lui_process_queue');
        }
    }
    public static function process(): void
    {
        $queue = get_option(self::OPTION, []);
        if (!is_array($queue) || !$queue) {
            return;
        }
        $changed = false;
        foreach ($queue as $key => $job) {
            if (!is_array($job) || (int) ($job['run_at'] ?? 0) > time()) {
                continue;
            }
            $changed = true;
            if (($job['type'] ?? '') === 'user_sync') {
                $result = (new LUI_User_Sync())->sync((int) ($job['user_id'] ?? 0));
                if (is_wp_error($result) && (int) ($job['attempt'] ?? 0) < 3) {
                    $next = (int) $job['attempt'] + 1;
                    $queue[$key] = ['type' => 'user_sync', 'user_id' => (int) $job['user_id'], 'attempt' => $next, 'run_at' => time() + (60 * (2 ** $next))];
                    LUI_Logger::log('warning', 'Queued user sync will be retried.', ['wp_user_id' => (int) $job['user_id'], 'attempt' => $next]);
                } else {
                    unset($queue[$key]);
                    if (is_wp_error($result)) {
                        LUI_Logger::log('error', 'User sync exhausted retries.', ['wp_user_id' => (int) ($job['user_id'] ?? 0), 'error' => $result->get_error_code()]);
                    }
                }
            } else {
                unset($queue[$key]);
            }
        }
        if ($changed) {
            update_option(self::OPTION, $queue, false);
        }
        if ($queue && !wp_next_scheduled('lui_process_queue')) {
            wp_schedule_single_event(time() + 60, 'lui_process_queue');
        }
    }
}
