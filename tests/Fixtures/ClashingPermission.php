<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use App\Support\Roles\SystemPermissionDefinition;

/** A stand-in add-on that redefines one of core's permission names. */
enum ClashingPermission: string implements SystemPermissionDefinition
{
    case MANAGE_USERS = 'manage users';

    public function label(): string
    {
        return 'Manage users';
    }

    public function category(): string
    {
        return 'Clashing';
    }

    /** @return list<SystemPermissionDefinition> */
    public function implies(): array
    {
        return [];
    }
}
