<?php

declare(strict_types=1);

namespace Concise\Teams\Enums;

/**
 * Who may create a team — `config('teams.creation')` (scope §7, D3). This axis
 * owns creation only; whether members or admins may invite is separate (see
 * InvitationPolicy / `config('teams.invitations')`), so the two can never
 * contradict each other. Read it through current() so the rules live here, not
 * as string comparisons at call sites.
 */
enum TeamCreationMode: string
{
    /** Any verified user may create a team for themselves. */
    case SELF_SERVICE = 'self-service';

    /** Only system admins create teams (Admin › Teams); users are added to them. */
    case ADMIN_ONLY = 'admin-only';

    public static function current(): self
    {
        return self::from((string) config('teams.creation', self::SELF_SERVICE->value));
    }

    /** May any verified user create a team for themselves? */
    public function allowsSelfServiceCreation(): bool
    {
        return $this === self::SELF_SERVICE;
    }
}
