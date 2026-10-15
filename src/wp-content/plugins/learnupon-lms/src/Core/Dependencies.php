<?php

declare(strict_types=1);

namespace LearnUponLMS\Core;

/**
 * Checks for the plugins this one depends on.
 */
final class Dependencies
{
    public static function woocommerceActive(): bool
    {
        return class_exists('WooCommerce');
    }

    public static function actionSchedulerAvailable(): bool
    {
        return function_exists('as_enqueue_async_action');
    }

    /**
     * @return string[] Human-readable list of missing requirements.
     */
    public static function missing(): array
    {
        $missing = [];

        if (!self::woocommerceActive()) {
            $missing[] = 'WooCommerce';
        }

        if (!self::actionSchedulerAvailable()) {
            $missing[] = 'Action Scheduler';
        }

        return $missing;
    }

    public static function satisfied(): bool
    {
        return self::missing() === [];
    }
}
