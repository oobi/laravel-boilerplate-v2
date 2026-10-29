<?php

declare(strict_types=1);

namespace Concise\Teams\Actions;

use App\Enums\SystemPermission;
use App\Models\User;
use Concise\Teams\Models\Team;
use Concise\Teams\Models\TeamInvitation;
use Concise\Teams\Support\InvitationPolicy;
use Concise\Teams\Support\TeamCoverage;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use InvalidArgumentException;
use RuntimeException;

/**
 * Invite an email address to a team: records (or refreshes) the pending
 * invitation, starts its expiry clock again and emails the signed accept
 * link. Resending a pending invitation comes through here too. The write
 * boundary for invitations. The UI's form rules give the same messages; this
 * enforces the same rules regardless of caller. Its refusals are translated,
 * since the invite dialog shows any that reach it as they are. Whether the
 * inviter is a system admin or a member decides which switch gates them (see
 * InvitationPolicy). Sending is throttled per inviter and per address within
 * a team (config teams.invitations.throttle), so it can't flood an inbox.
 */
class InviteMember
{
    /**
     * @throws InvalidArgumentException when the address or role can't be invited
     * @throws ThrottleRequestsException when the inviter, or this team to the address, has sent too many this hour
     */
    public function __invoke(Team $team, string $email, ?string $role = null, ?User $inviter = null): TeamInvitation
    {
        $isSystemAdmin = $inviter !== null && Gate::forUser($inviter)->allows(SystemPermission::MANAGE_TEAMS->value);

        $allowed = $isSystemAdmin ? InvitationPolicy::adminsMayInvite() : InvitationPolicy::membersMayInvite();

        if (! $allowed) {
            throw new RuntimeException('Invitations are disabled for this caller (config teams.invitations).');
        }

        $email = (string) User::normalizeEmail($email);

        if ($team->users()->where('email', $email)->exists()) {
            throw new InvalidArgumentException(team_trans('invitations.already_member'));
        }

        if ($role !== null && ! Team::availableRoles()->where('name', $role)->exists()) {
            throw new InvalidArgumentException(team_trans('invitations.not_a_role', ['role' => $role]));
        }

        // A member inviting may only offer a role they hold everything of (TeamCoverage).
        if ($role !== null && $inviter !== null && ! TeamCoverage::coversRoleNamed($inviter, $team, $role)) {
            throw new InvalidArgumentException(team_trans('invitations.role_not_allowed', ['role' => $role]));
        }

        $this->throttle($team, $email, $inviter);

        // Re-inviting the same address refreshes the role and expiry and re-sends, rather than failing on the unique index.
        $invitation = $team->invitations()->updateOrCreate(['email' => $email], [
            'role' => $role,
            'expires_at' => now()->addDays(TeamInvitation::lifetimeInDays()),
        ]);

        $invitation->send();

        return $invitation;
    }

    /**
     * Count this send against the inviter and against this team's sends to
     * the address, refusing it once either has reached its hourly limit. The
     * address limit is per team so one team can't use it up and block another
     * team's invitations. Only sends that pass every other check are counted.
     *
     * @throws ThrottleRequestsException
     */
    private function throttle(Team $team, string $email, ?User $inviter): void
    {
        $limits = ['invite-address:'.$team->getKey().':'.sha1($email) => max(1, (int) config('teams.invitations.throttle.per_address'))];

        if ($inviter !== null) {
            $limits['invite-inviter:'.$inviter->getKey()] = max(1, (int) config('teams.invitations.throttle.per_inviter'));
        }

        foreach ($limits as $key => $perHour) {
            if (RateLimiter::tooManyAttempts($key, $perHour)) {
                throw new ThrottleRequestsException(team_trans('invitations.throttled'));
            }
        }

        foreach (array_keys($limits) as $key) {
            RateLimiter::increment($key, 3600);
        }
    }
}
