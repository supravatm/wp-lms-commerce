<?php

declare(strict_types=1);

namespace LearnUponLMS\Core;

/**
 * Custom roles mirroring LearnUpon user types.
 *
 * Each role gets the same capabilities as WooCommerce's "customer" role
 * (or just "read" if that role doesn't exist). None of them grant
 * WordPress admin access; the 'admin' role here is separate from the
 * built-in 'administrator' role.
 */
final class Roles
{
    public const LEARNER = 'learner';
    public const ADMIN = 'admin';
    public const INSTRUCTOR = 'instructor';
    public const MANAGER = 'manager';

    /**
     * @return array<string, string> Role slug => display name.
     */
    public static function all(): array
    {
        return [
            self::LEARNER    => __('Learner', 'learnupon-lms'),
            self::ADMIN      => __('Admin', 'learnupon-lms'),
            self::INSTRUCTOR => __('Instructor', 'learnupon-lms'),
            self::MANAGER    => __('Manager', 'learnupon-lms'),
        ];
    }

    public static function register(): void
    {
        $customer = get_role('customer');
        $caps = $customer ? $customer->capabilities : ['read' => true];

        foreach (self::all() as $slug => $label) {
            if (!get_role($slug)) {
                add_role($slug, $label, $caps);
            }
        }
    }
}
