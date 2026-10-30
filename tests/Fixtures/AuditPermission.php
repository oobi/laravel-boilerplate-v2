<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use App\Enums\SystemPermission;
use App\Support\Roles\SystemPermissionDefinition;

/** A stand-in add-on's system permissions, for testing SystemPermissionRegistry without the teams tier. */
enum AuditPermission: string implements SystemPermissionDefinition
{
    case VIEW_AUDIT_LOG = 'view audit log';
    case EXPORT_AUDIT_LOG = 'export audit log';

    public function label(): string
    {
        return match ($this) {
            self::VIEW_AUDIT_LOG => 'View audit log',
            self::EXPORT_AUDIT_LOG => 'Export audit log',
        };
    }

    public function category(): string
    {
        return 'Audit log';
    }

    /** @return list<SystemPermissionDefinition> */
    public function implies(): array
    {
        return match ($this) {
            self::VIEW_AUDIT_LOG => [SystemPermission::ACCESS_ADMIN_PANEL],
            self::EXPORT_AUDIT_LOG => [self::VIEW_AUDIT_LOG],
        };
    }
}
