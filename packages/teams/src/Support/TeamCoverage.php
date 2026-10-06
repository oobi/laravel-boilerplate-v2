<?php

declare(strict_types=1);

namespace Concise\Teams\Support;

use App\Enums\SystemPermission;
use App\Models\Role;
use App\Models\User;
use Concise\Teams\Models\Team;
use Illuminate\Support\Collection;

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
     * The team permissions the user holds in this team through their roles.
     *
     * @return Collection<int, string>
     */
    public static function permissionsOf(Team $team, User $user): Collection
    {
        if ($team->isSuspended($user)) {
            return collect();
        }

        return Team::availableRoles()->whereIn('name', $team->rolesFor($user))->with('permissions')->get()
            ->flatMap(fn (Role $role): Collection => $role->permissions->pluck('name'))
            ->unique()
            ->values();
    }

    /** Whether the actor holds every team permission this role carries, in this team. */
    public static function coversRole(User $actor, Team $team, Role $role): bool
    {
        return self::isHeadOffice($actor)
            || $role->permissions->pluck('name')->diff(self::permissionsOf($team, $actor))->isEmpty();
    }

    /** Whether the actor holds every team permission the member holds, in this team. */
    public static function coversMember(User $actor, Team $team, User $member): bool
    {
        return self::isHeadOffice($actor)
            || self::permissionsOf($team, $member)->diff(self::permissionsOf($team, $actor))->isEmpty();
    }

    /** Whether the actor covers the team role with this name. */
    public static function coversRoleNamed(User $actor, Team $team, string $name): bool
    {
        $role = Team::availableRoles()->where('name', $name)->with('permissions')->first();

        return $role !== null && self::coversRole($actor, $team, $role);
    }

    private static function isHeadOffice(User $actor): bool
    {
        return $actor->isSuperAdmin() || $actor->hasSystemPermission(SystemPermission::MANAGE_TEAMS);
    }
}
