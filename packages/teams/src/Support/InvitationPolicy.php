<?php

declare(strict_types=1);

namespace Concise\Teams\Support;

/**
 * Whether email invitations are available, and to whom — `config('teams.invitations')`.
 * Two independent switches (scope §7): members/owners inviting from the team
 * area, and system admins inviting from Admin › Teams. A backoffice turns both
 * off (admins create accounts and add them); a SaaS leaves both on. Kept off
 * TeamCreationMode on purpose so creation and invitation policy stay orthogonal.
 */
final class InvitationPolicy
{
    /** May a team's owner/admins invite people from the team area? */
    public static function membersMayInvite(): bool
    {
        return (bool) config('teams.invitations.members', true);
    }

    /** May a system admin invite people from Admin › Teams? */
    public static function adminsMayInvite(): bool
    {
        return (bool) config('teams.invitations.admins', true);
    }

    /** Is the invitation feature present at all (either party may invite)? */
    public static function anyEnabled(): bool
    {
        return self::membersMayInvite() || self::adminsMayInvite();
    }
}
