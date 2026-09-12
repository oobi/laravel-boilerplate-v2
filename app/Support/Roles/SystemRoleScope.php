<?php

declare(strict_types=1);

namespace App\Support\Roles;

use App\Enums\SystemPermission;
use App\Models\Role;

/** The app-wide roles: what a user can do across the whole system (SystemPermission). */
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
        return collect(SystemPermission::byCategory())
            ->map(fn (array $permissions): array => collect($permissions)
                ->mapWithKeys(fn (SystemPermission $permission): array => [$permission->value => $permission->label()])
                ->all())
            ->all();
    }

    /** No implications between system permissions. */
    public function implications(): array
    {
        return [];
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
}
