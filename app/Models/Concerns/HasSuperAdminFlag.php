<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Enums\SystemPermission;

/**
 * The one hardcoded, non-spatie authorization concept in the app — see
 * .ai/rules/models.md for why this is deliberately kept outside the
 * admin-configurable role system.
 */
trait HasSuperAdminFlag
{
    public function isSuperAdmin(): bool
    {
        return (bool) $this->is_super_admin;
    }

    public function canAccessAdmin(): bool
    {
        return $this->isSuperAdmin() || $this->checkPermissionTo(SystemPermission::ACCESS_ADMIN_PANEL->value);
    }
}
