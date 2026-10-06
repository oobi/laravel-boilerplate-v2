<?php

declare(strict_types=1);

namespace App\Support\Roles;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * The guard against raising access through the admin screens: you may act on
 * a user or a system role only if you "cover" it, holding every system
 * permission it carries. So Support can't hand out Administrator, edit an
 * Administrator, or strip one, and nobody can add to a role a permission they
 * don't hold. Peers (the same access) cover each other; a super admin untangles.
 *
 * A super admin covers everyone and every role; nobody else covers a super
 * admin, whose protection is the flag, not a permission. The teams tier has
 * the same rule in team permissions (Concise\Teams\Support\TeamCoverage).
 */
final class Coverage
{
    /**
     * Every system permission the user holds, through roles or directly.
     *
     * @return Collection<int, string>
     */
    public static function permissionsOf(User $user): Collection
    {
        return $user->getAllPermissions()->pluck('name')->values();
    }

    /** Whether the actor holds every one of these permission names. */
    public static function covers(User $actor, iterable $permissions): bool
    {
        if ($actor->isSuperAdmin()) {
            return true;
        }

        return collect($permissions)->diff(self::permissionsOf($actor))->isEmpty();
    }

    /** Whether the actor may act on this user: holds all they hold, and they're not a super admin. */
    public static function coversUser(User $actor, User $target): bool
    {
        if ($actor->isSuperAdmin()) {
            return true;
        }

        return ! $target->isSuperAdmin() && self::covers($actor, self::permissionsOf($target));
    }

    /** Whether the actor holds every permission this role carries. */
    public static function coversRole(User $actor, Role $role): bool
    {
        return self::covers($actor, $role->permissions->pluck('name'));
    }

    /**
     * Whether the actor may change this system role on the Roles screen:
     * they cover it as it is and as it would be, cover everyone who holds it (the
     * change reaches them all), and don't hold it themselves (editing your own
     * role is how a role would grow itself).
     *
     * @param  iterable<string>  $newPermissions
     */
    public static function mayEditRole(User $actor, Role $role, iterable $newPermissions = []): bool
    {
        if ($actor->isSuperAdmin()) {
            return true;
        }

        return ! $actor->hasRole($role)
            && self::coversRole($actor, $role)
            && self::covers($actor, $newPermissions)
            // A change reaches everyone holding the role: none may be above the editor.
            && $role->users->every(fn (User $holder): bool => self::coversUser($actor, $holder));
    }
}
