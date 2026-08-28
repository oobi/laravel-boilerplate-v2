<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Enums\SystemPermission;
use App\Enums\SystemRole;

trait HasSystemRole
{
    public function hasSystemRole(SystemRole $role): bool
    {
        return $this->system_role === $role;
    }

    public function hasAnySystemRole(): bool
    {
        return $this->system_role !== null;
    }

    public function isSuperAdmin(): bool
    {
        return $this->system_role === SystemRole::SUPER_ADMIN;
    }

    public function isSupport(): bool
    {
        return $this->system_role === SystemRole::SUPPORT;
    }

    public function hasSystemPermission(SystemPermission $permission): bool
    {
        if (! $this->system_role instanceof SystemRole) {
            return false;
        }

        return in_array($permission, $this->system_role->defaultPermissions(), strict: true);
    }

    public function canAccessAdmin(): bool
    {
        return $this->hasSystemPermission(SystemPermission::ACCESS_ADMIN_PANEL);
    }
}
