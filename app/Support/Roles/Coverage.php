<?php

declare(strict_types=1);

namespace App\Support\Roles;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use WeakMap;

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
    /** @var WeakMap<User, Collection<int, string>>|null */
    private static ?WeakMap $permissions = null;

    /** @var WeakMap<User, array<string, list<string>>>|null */
    private static ?WeakMap $rolePermissions = null;

    /**
     * Every system permission the user holds, through roles or directly.
     * Remembered against the User object (a WeakMap, so it goes when the object
     * does): a page asks about the same people many times. Cached per object,
     * so after changing someone's access use a fresh instance to see it.
     *
     * @return Collection<int, string>
     */
    public static function permissionsOf(User $user): Collection
    {
        self::$permissions ??= new WeakMap;

        return self::$permissions[$user] ??= $user->getAllPermissions()->pluck('name')->values();
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

    /** Whether the actor covers the system role with this name (an unknown name, never). */
    public static function coversRoleNamed(User $actor, string $name): bool
    {
        // Every system role's permissions in one query, remembered against the viewer.
        self::$rolePermissions ??= new WeakMap;
        $map = self::$rolePermissions[$actor] ??= Role::systemRoles()->with('permissions')->get()
            ->mapWithKeys(fn (Role $role): array => [$role->name => $role->permissions->pluck('name')->all()])
            ->all();

        return array_key_exists($name, $map) && self::covers($actor, $map[$name]);
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
            && ! self::anyHolderAbove($actor, $role);
    }

    /**
     * Whether anyone holding the role is a super admin or holds a permission
     * the actor doesn't: coversUser() for every holder, as one query.
     */
    private static function anyHolderAbove(User $actor, Role $role): bool
    {
        $held = self::permissionsOf($actor)->all();

        return $role->users()
            ->where(fn (Builder $holders) => $holders
                ->where('users.is_super_admin', true)
                ->orWhereHas('permissions', fn (Builder $permissions) => $permissions->whereNotIn('permissions.name', $held))
                ->orWhereHas('roles.permissions', fn (Builder $permissions) => $permissions->whereNotIn('permissions.name', $held)))
            ->exists();
    }
}
