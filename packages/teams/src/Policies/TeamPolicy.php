<?php

declare(strict_types=1);

namespace Concise\Teams\Policies;

use App\Models\User;
use Concise\Teams\Models\Team;

/**
 * Per-instance team abilities. The super-admin bypass (a global Gate::before in
 * AppServiceProvider) still applies, so these only decide access for normal users.
 * "Admin-level" = the team owner or a member holding the owner/admin team role.
 */
class TeamPolicy
{
    public function view(User $user, Team $team): bool
    {
        return $team->hasUser($user);
    }

    public function manageMembers(User $user, Team $team): bool
    {
        return $team->userIsAdmin($user);
    }

    public function invite(User $user, Team $team): bool
    {
        return $team->userIsAdmin($user);
    }

    public function update(User $user, Team $team): bool
    {
        return $team->userIsAdmin($user);
    }

    public function delete(User $user, Team $team): bool
    {
        return $team->user_id === $user->getKey();
    }
}
