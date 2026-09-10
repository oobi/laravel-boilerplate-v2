<?php

declare(strict_types=1);

namespace Concise\Teams\Policies;

use App\Models\User;
use Concise\Teams\Enums\TeamPermission;
use Concise\Teams\Models\Team;

/**
 * Per-instance team abilities, mirroring UserPolicy's scheme: coarse checks go
 * through spatie permissions (TeamPermission, resolved in the team's scope),
 * never role names. The team owner is the structural "super admin of the team"
 * and bypasses every ability within their own team via before() — the team-level
 * parallel of the global super-admin Gate::before (which still applies here too).
 */
class TeamPolicy
{
    public function before(User $user, string $ability, mixed $team = null): ?bool
    {
        return $team instanceof Team && $team->isOwnedBy($user) ? true : null;
    }

    public function view(User $user, Team $team): bool
    {
        return $team->hasUser($user);
    }

    public function manageMembers(User $user, Team $team): bool
    {
        return $team->memberHasPermission($user, TeamPermission::MANAGE_MEMBERS);
    }

    public function invite(User $user, Team $team): bool
    {
        return $team->memberHasPermission($user, TeamPermission::INVITE_MEMBERS);
    }

    public function update(User $user, Team $team): bool
    {
        return $team->memberHasPermission($user, TeamPermission::UPDATE_TEAM);
    }

    /** Deleting a team is the owner's alone — granted by before(), never by a role. */
    public function delete(User $user, Team $team): bool
    {
        return false;
    }
}
