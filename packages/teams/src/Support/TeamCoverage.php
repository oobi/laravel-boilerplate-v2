<?php

declare(strict_types=1);

namespace Concise\Teams\Support;

use App\Enums\SystemPermission;
use App\Models\Role;
use App\Models\User;
use Concise\Teams\Models\Team;
use Illuminate\Support\Collection;
use WeakMap;

/**
 * The team-level counterpart of App\Support\Roles\Coverage: a member may give,
 * take away or act on a team role or member only if they hold every team
 * permission it carries, in this team. So someone who can manage members
 * can't hand out a role with more than they have (a manager-level role from
 * a staff-level one), or act on a member above them. Peers cover each other.
 *
 * A system admin running teams (a super admin, or "manage teams") covers every
 * role and member, as TeamPolicy::before() already grants them everything.
 * Owners keep their own shield (MembersTable::canActOn()).
 */
final class TeamCoverage
{
    /**
     * Remembered against the objects they describe (WeakMaps, so an entry goes
     * when its object does): a page asks about the same people many times.
     *
     * @var WeakMap<User, array<int|string, Collection<int, string>>>|null
     */
    private static ?WeakMap $permissions = null;

    /** @var WeakMap<User, bool>|null */
    private static ?WeakMap $headOffice = null;

    /** @var WeakMap<Team, array<string, list<string>>>|null */
    private static ?WeakMap $rolePermissions = null;

    /**
     * The team permissions the user holds in this team through their roles.
     * Cached per object, so after changing someone's roles use a fresh instance.
     *
     * @return Collection<int, string>
     */
    public static function permissionsOf(Team $team, User $user): Collection
    {
        self::$permissions ??= new WeakMap;
        $byTeam = self::$permissions[$user] ?? [];

        if (! array_key_exists($team->getKey(), $byTeam)) {
            $byTeam[$team->getKey()] = $team->isSuspended($user)
                ? collect()
                : self::permissionsOfRoles($team, $team->rolesFor($user));
            self::$permissions[$user] = $byTeam;
        }

        return $byTeam[$team->getKey()];
    }

    /**
     * Whether the actor holds every team permission these roles carry, in this
     * team: the cheap check for a list that already has each member's roles.
     *
     * @param  iterable<string>  $names
     */
    public static function coversRoleNames(User $actor, Team $team, iterable $names): bool
    {
        return self::isHeadOffice($actor)
            || self::permissionsOfRoles($team, $names)->diff(self::permissionsOf($team, $actor))->isEmpty();
    }

    /** Whether the actor holds every team permission this role carries, in this team. */
    public static function coversRole(User $actor, Team $team, Role $role): bool
    {
        return self::coversRoleNames($actor, $team, [$role->name]);
    }

    /** Whether the actor holds every team permission the member holds, in this team. */
    public static function coversMember(User $actor, Team $team, User $member): bool
    {
        return self::isHeadOffice($actor)
            || self::permissionsOf($team, $member)->diff(self::permissionsOf($team, $actor))->isEmpty();
    }

    /** Whether the actor covers the team role with this name (an unknown name, never). */
    public static function coversRoleNamed(User $actor, Team $team, string $name): bool
    {
        return array_key_exists($name, self::rolePermissions($team)) && self::coversRoleNames($actor, $team, [$name]);
    }

    /**
     * Every team permission these team roles carry.
     *
     * @param  iterable<string>  $names
     * @return Collection<int, string>
     */
    private static function permissionsOfRoles(Team $team, iterable $names): Collection
    {
        $map = self::rolePermissions($team);

        return collect($names)->flatMap(fn (string $name): array => $map[$name] ?? [])->unique()->values();
    }

    /**
     * Team role name => the permission names it carries: every team role in
     * one query, remembered against the Team object.
     *
     * @return array<string, list<string>>
     */
    private static function rolePermissions(Team $team): array
    {
        self::$rolePermissions ??= new WeakMap;

        return self::$rolePermissions[$team] ??= Team::availableRoles()->with('permissions')->get()
            ->mapWithKeys(fn (Role $role): array => [$role->name => $role->permissions->pluck('name')->all()])
            ->all();
    }

    private static function isHeadOffice(User $actor): bool
    {
        self::$headOffice ??= new WeakMap;

        return self::$headOffice[$actor] ??= $actor->isSuperAdmin() || $actor->hasSystemPermission(SystemPermission::MANAGE_TEAMS);
    }
}
