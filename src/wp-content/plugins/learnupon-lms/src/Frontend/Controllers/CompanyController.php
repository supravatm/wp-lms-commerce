<?php

declare(strict_types=1);

namespace LearnUponLMS\Frontend\Controllers;

use LearnUponLMS\Core\Roles;

final class CompanyController extends RoleGatedController
{
    protected function allowedRoles(): array
    {
        return [Roles::ADMIN];
    }
}
