<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use App\Enums\SystemPermission;
use App\Support\Roles\SystemPermissionDefinition;

/** A stand-in add-on whose category label differs from core's "User management" only in case, so both slug the same. */
enum UserExportPermission: string implements SystemPermissionDefinition
{
    case EXPORT_USERS = 'export users';

    public function label(): string
    {
        return 'Export users';
    }

    public function category(): string
    {
        return 'User Management';
    }

    /** @return list<SystemPermissionDefinition> */
    public function implies(): array
    {
        return [SystemPermission::VIEW_USERS];
    }
}
