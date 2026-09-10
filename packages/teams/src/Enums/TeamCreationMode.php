<?php

declare(strict_types=1);

namespace Concise\Teams\Enums;

/**
 * Who may create teams and how members join — `config('teams.creation')`
 * (scope §7, D3). Read it through current() so the rules live here, not as
 * string comparisons at call sites.
 */
enum TeamCreationMode: string
{
    /** Users create teams and invite others. */
    case SELF_SERVICE = 'self-service';

    /** Only system admins create teams and assign members. */
    case ADMIN_PROVISIONED = 'admin-provisioned';

    /** Admins provision teams; owners/admins invite members. */
    case INVITATION_ONLY = 'invitation-only';

    public static function current(): self
    {
        return self::from((string) config('teams.creation', self::SELF_SERVICE->value));
    }

    /** May a team's owner/admins invite members from the team area? */
    public function allowsMemberInvitations(): bool
    {
        return $this !== self::ADMIN_PROVISIONED;
    }

    /** May any verified user create a team for themselves? */
    public function allowsSelfServiceCreation(): bool
    {
        return $this === self::SELF_SERVICE;
    }
}
