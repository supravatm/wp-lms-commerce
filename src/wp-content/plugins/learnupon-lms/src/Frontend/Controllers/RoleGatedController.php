<?php

declare(strict_types=1);

namespace LearnUponLMS\Frontend\Controllers;

/**
 * Base class for My Account endpoint controllers gated by user role.
 */
abstract class RoleGatedController
{
    /**
     * Roles allowed to access this endpoint.
     *
     * @return string[]
     */
    abstract protected function allowedRoles(): array;

    public function canAccess(): bool
    {
        if (!is_user_logged_in()) {
            return false;
        }

        $user = wp_get_current_user();

        return (bool) array_intersect($this->allowedRoles(), (array) $user->roles);
    }
}
