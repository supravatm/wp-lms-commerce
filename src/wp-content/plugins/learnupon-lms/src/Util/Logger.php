<?php

declare(strict_types=1);

namespace LearnUponLMS\Util;

final class Logger
{
    private const SOURCE = 'learnupon-lms';

    public static function info(string $message, array $context = []): void
    {
        self::log('info', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::log('warning', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::log('error', $message, $context);
    }

    public static function debug(string $message, array $context = []): void
    {
        self::log('debug', $message, $context);
    }

    public static function critical(string $message, array $context = []): void
    {
        self::log('critical', $message, $context);
    }

    private static function log(
        string $level,
        string $message,
        array $context = []
    ): void {
        if (!function_exists('wc_get_logger')) {
            error_log('[LearnUponLMS] ' . strtoupper($level) . ': ' . $message);
            return;
        }

        $context['source'] = self::SOURCE;

        wc_get_logger()->log($level, $message, $context);
    }
}
