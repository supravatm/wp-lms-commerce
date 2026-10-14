<?php

declare(strict_types=1);

namespace LearnUponLMS\Controllers;

class MyLearningController
{
    public function canAccess(): bool
    {
        if (!is_user_logged_in()) {
            return false;
        }
        $user = wp_get_current_user();
        $allowedRoles = [
            'customer'
        ];
        return (bool) array_intersect(
            $allowedRoles,
            (array) $user->roles
        );
    }
}
