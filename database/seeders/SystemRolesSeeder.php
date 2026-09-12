<?php

namespace Database\Seeders;

use App\Enums\SystemPermission;
use App\Models\Role;
use App\Support\Theme\DaisyColor;
use Illuminate\Database\Seeder;
use Spatie\Permission\Guard;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeds the default system roles — the app-wide counterpart of TeamRolesSeeder.
 * A fresh install starts with an "Administrator" (every system capability, so
 * routine admin work never needs the super-admin flag — super admin stays the
 * exception) and a limited "Support"; edit this list to change the defaults.
 *
 * Roles are seeded ONLY into an empty set. After that the database is the
 * source of truth: rename, delete or replace them freely and nothing here
 * resurrects them. The super admin is a flag, never a role, so it isn't seeded;
 * the permission vocabulary itself is PermissionSeeder's job.
 */
class SystemRolesSeeder extends Seeder
{
    /**
     * Role name => badge colour and the SystemPermission cases it holds.
     *
     * @return array<string, array{color: DaisyColor, permissions: list<SystemPermission>}>
     */
    protected function defaults(): array
    {
        return [
            // Everything a role can grant. The gap from a super admin is exactly
            // the acts that aren't permissions — managing roles, granting super
            // admin, direct password resets, the policy bypass itself — so an
            // Administrator runs the platform without holding the master key.
            'Administrator' => [
                'color' => DaisyColor::ERROR,
                'permissions' => SystemPermission::cases(),
            ],
            // A limited desk role: reach the panel, manage and suspend users, and
            // impersonate to reproduce a problem — not delete, settings or teams.
            'Support' => [
                'color' => DaisyColor::WARNING,
                'permissions' => [
                    SystemPermission::ACCESS_ADMIN_PANEL,
                    SystemPermission::MANAGE_USERS,
                    SystemPermission::SUSPEND_USERS,
                    SystemPermission::IMPERSONATE_USERS,
                ],
            ],
        ];
    }

    public function run(): void
    {
        if (Role::systemRoles()->exists()) {
            return;
        }

        $guard = Guard::getDefaultName(Role::class);

        foreach ($this->defaults() as $name => $role) {
            $created = Role::create([
                'name' => $name,
                'guard_name' => $guard,
                'color' => $role['color'],
            ]);

            $created->syncPermissions(array_map(
                fn (SystemPermission $permission): Permission => Permission::findOrCreate($permission->value, $guard),
                $role['permissions'],
            ));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
