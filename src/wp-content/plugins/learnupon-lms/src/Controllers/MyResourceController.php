<?php

declare(strict_types=1);

namespace LearnUponLMS\Controllers;

class MyResourceController
{
    public function canAccess(): bool
    {
        if (!is_user_logged_in()) {
            return false;
        }
        $user = wp_get_current_user();
        $allowedRoles = [
            'group_manager'
        ];
        return (bool) array_intersect(
            $allowedRoles,
            (array) $user->roles
        );
    }
}
