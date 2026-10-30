<?php

declare(strict_types=1);

namespace App\Support\Roles;

use App\Models\Role;
use App\Models\User;

/** The app-wide roles: what a user can do across the whole system (SystemPermissionRegistry: core's SystemPermission plus add-ons'). */
final class SystemRoleScope implements RoleScope
{
    public function key(): string
    {
        return Role::SYSTEM_SCOPE;
    }

    public function label(): string
    {
        return __('admin.system_roles');
    }

    public function description(): string
    {
        return __('admin.roles_description');
    }

    public function permissions(): array
    {
        return collect(SystemPermissionRegistry::byCategory())
            ->map(fn (array $permissions): array => collect($permissions)
                ->mapWithKeys(fn (SystemPermissionDefinition $permission): array => [$permission->value => $permission->label()])
                ->all())
            ->all();
    }

    /** An action carries its area's read floor, a read floor carries panel entry, per each definition's implies(). */
    public function implications(): array
    {
        return SystemPermissionRegistry::implicationMap();
    }

    /** Every system permission always applies. */
    public function unavailable(): array
    {
        return [];
    }

    /** Role's own defaults already describe a system role. */
    public function attributes(): array
    {
        return [];
    }

    public function order(): int
    {
        return 0;
    }

    /** Anyone with "manage roles"; which roles they may change is Coverage::mayEditRole()'s call. */
    public function mayManage(User $actor): bool
    {
        return true;
    }

    public function holderCount(Role $role): int
    {
        return $role->users()->count();
    }
}
