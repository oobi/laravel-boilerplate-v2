<?php

declare(strict_types=1);

namespace Concise\Teams\Actions;

use App\Enums\SystemPermission;
use App\Models\User;
use Concise\Teams\Models\Team;
use Concise\Teams\Models\TeamInvitation;
use Concise\Teams\Support\InvitationPolicy;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use RuntimeException;

/**
 * Invite an email address to a team: records (or refreshes) the pending
 * invitation and emails the signed accept link. The write boundary for
 * invitations — the UI's form rules give friendlier messages, this enforces
 * the same rules regardless of caller. Whether the inviter is a system admin
 * or a member decides which switch gates them (see InvitationPolicy).
 */
class InviteMember
{
    public function __invoke(Team $team, string $email, ?string $role = null, ?User $inviter = null): TeamInvitation
    {
        $isSystemAdmin = $inviter !== null && Gate::forUser($inviter)->allows(SystemPermission::MANAGE_TEAMS->value);

        $allowed = $isSystemAdmin ? InvitationPolicy::adminsMayInvite() : InvitationPolicy::membersMayInvite();

        if (! $allowed) {
            throw new RuntimeException('Invitations are disabled for this caller (config teams.invitations).');
        }

        $email = (string) User::normalizeEmail($email);

        if ($team->users()->where('email', $email)->exists()) {
            throw new InvalidArgumentException(sprintf('%s is already a member of %s.', $email, $team->name));
        }

        if ($role !== null && ! Team::availableRoles()->where('name', $role)->exists()) {
            throw new InvalidArgumentException(sprintf("'%s' is not a team role.", $role));
        }

        // Re-inviting the same address refreshes the role and re-sends, rather than failing on the unique index.
        $invitation = $team->invitations()->updateOrCreate(['email' => $email], ['role' => $role]);

        $invitation->send();

        return $invitation;
    }
}
