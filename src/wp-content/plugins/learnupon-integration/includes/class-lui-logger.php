<?php
if (!defined('ABSPATH')) { exit; }

final class LUI_Logger {
    public static function log(string $level, string $message, array $context = []): void {
        $safe = [];
        foreach ($context as $key => $value) {
            if (preg_match('/pass|secret|token|authorization|api.?key|credential/i', (string) $key)) {
                $safe[$key] = '[redacted]';
            } elseif (is_scalar($value) || $value === null) {
                $safe[$key] = $value;
            } else {
                $safe[$key] = wp_json_encode($value);
            }
        }
        $line = '[LearnUpon Integration][' . strtoupper($level) . '] ' . $message;
        if ($safe) { $line .= ' ' . wp_json_encode($safe); }
        if (function_exists('wc_get_logger')) {
            wc_get_logger()->log($level, $message . ($safe ? ' ' . wp_json_encode($safe) : ''), ['source' => 'learnupon-integration']);
        } else {
            error_log($line); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        }
    }
}
