<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use App\Enums\SystemPermission;
use App\Support\Roles\SystemPermissionDefinition;

/** A stand-in add-on's system permissions, for testing SystemPermissionRegistry without the teams tier. */
enum ReportPermission: string implements SystemPermissionDefinition
{
    case VIEW_REPORTS = 'view reports';
    case EXPORT_REPORTS = 'export reports';

    public function label(): string
    {
        return match ($this) {
            self::VIEW_REPORTS => 'View reports',
            self::EXPORT_REPORTS => 'Export reports',
        };
    }

    public function category(): string
    {
        return 'Reports';
    }

    /** @return list<SystemPermissionDefinition> */
    public function implies(): array
    {
        return match ($this) {
            self::VIEW_REPORTS => [SystemPermission::ACCESS_ADMIN_PANEL],
            self::EXPORT_REPORTS => [self::VIEW_REPORTS],
        };
    }
}
