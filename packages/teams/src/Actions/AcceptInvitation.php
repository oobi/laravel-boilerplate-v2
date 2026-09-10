<?php

declare(strict_types=1);

namespace Concise\Teams\Actions;

use App\Models\User;
use Concise\Teams\Models\Team;
use Concise\Teams\Models\TeamInvitation;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Accept an invitation as the signed-in user: they join the team with the
 * invited role (or none, if that role has since been deleted), the invitation
 * is consumed, and the team becomes their current one.
 */
class AcceptInvitation
{
    /**
     * @throws AuthorizationException when the account's email isn't the invited one
     */
    public function __invoke(TeamInvitation $invitation, User $user): Team
    {
        if (! $invitation->isFor($user)) {
            throw new AuthorizationException(__('This invitation was sent to a different email address.'));
        }

        $team = $invitation->team;

        $role = $invitation->role !== null && Team::availableRoles()->where('name', $invitation->role)->exists()
            ? $invitation->role
            : null;

        $team->addMember($user, $role);
        $invitation->delete();
        $user->switchTeam($team);

        return $team;
    }
}
