<?php

declare(strict_types=1);

namespace Concise\Teams\Actions;

use App\Models\User;
use App\Support\Audit\Audit;
use App\Support\Audit\AuditCategory;
use Concise\Teams\Models\Team;
use Concise\Teams\Models\TeamInvitation;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Accept an invitation as the signed-in user: they join the team with the
 * invited role (or none, if that role has since been deleted), the invitation
 * is consumed, and the team becomes their current one. Following the signed
 * link sent to their address is proof of control of it, so an unverified
 * account is marked verified — the same standard as Laravel's own
 * verification link.
 */
class AcceptInvitation
{
    /**
     * @throws AuthorizationException when the invitation has expired or the account's email isn't the invited one
     */
    public function __invoke(TeamInvitation $invitation, User $user): Team
    {
        if ($invitation->isExpired()) {
            throw new AuthorizationException(team_trans('invitations.expired.title'));
        }

        if (! $invitation->isFor($user)) {
            throw new AuthorizationException(team_trans('invitations.wrong_account'));
        }

        $team = $invitation->team;

        if (! $team->active) {
            throw new AuthorizationException(team_trans('inactive'));
        }

        $role = $invitation->role !== null && Team::availableRoles()->where('name', $invitation->role)->exists()
            ? $invitation->role
            : null;

        DB::transaction(function () use ($team, $user, $role, $invitation): void {
            $team->addMember($user, $role);

            // The named entry below says it better than a team_invitation.deleted beside it.
            Audit::withoutLogging(fn () => $invitation->delete());

            Audit::record('team.invitation_accepted', $team, ['member_id' => $user->getKey(), 'member' => $user->email], category: AuditCategory::ACCESS);

            if (! $user->hasVerifiedEmail()) {
                $user->markEmailAsVerified();
            }
        });

        return $team;
    }
}
