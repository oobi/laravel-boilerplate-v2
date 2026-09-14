<?php

namespace Database\Seeders;

use App\Enums\SystemPermission;
use App\Models\Role;
use App\Support\Theme\DaisyColor;
use Illuminate\Database\Seeder;
use RuntimeException;
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
    /** The seeded Administrator role's name — what `bp:make-admin --administrator` assigns. */
    public const ADMINISTRATOR = 'Administrator';

    /**
     * Role name => badge colour and the SystemPermission cases it holds. Public
     * and static so it is the ONE definition of these roles: UserFactory's
     * `support()` state builds its fixture from it rather than repeating the list.
     *
     * @return array<string, array{color: DaisyColor, permissions: list<SystemPermission>}>
     */
    public static function defaults(): array
    {
        return [
            // Everything a role can grant. The gap from a super admin is exactly
            // the acts that aren't permissions — managing roles, granting super
            // admin, direct password resets, the policy bypass itself — so an
            // Administrator runs the platform without holding the master key.
            self::ADMINISTRATOR => [
                'color' => DaisyColor::ERROR,
                'permissions' => SystemPermission::cases(),
            ],
            // A limited desk role: manage and suspend users, and impersonate to
            // reproduce a problem — not delete, settings or teams. The read floor
            // (`view users`) and panel entry come with those through implies().
            'Support' => [
                'color' => DaisyColor::WARNING,
                'permissions' => [
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

        foreach (self::defaults() as $name => $role) {
            // Role names are unique per guard across every scope (an add-on's
            // team roles share the table): a clash is a real conflict — say so,
            // as Team::createRole() does, rather than surface a constraint error.
            $existing = Role::query()->where('name', $name)->where('guard_name', $guard)->first();

            if ($existing !== null) {
                throw new RuntimeException(sprintf(
                    "Cannot seed system role '%s': a role with that name already exists (scope '%s') and role names are unique.",
                    $name,
                    $existing->scope,
                ));
            }

            $created = Role::create([
                'name' => $name,
                'guard_name' => $guard,
                'color' => $role['color'],
            ]);

            // Closed over implications like every write path (an action carries its
            // area's read floor, a read floor carries panel entry), so a seeded grant
            // is always reachable.
            $created->syncPermissions(collect(SystemPermission::withImplied($role['permissions']))
                ->map(fn (SystemPermission $permission): Permission => Permission::findOrCreate($permission->value, $guard))
                ->all());
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
