<?php

declare(strict_types=1);

namespace Concise\Teams\Support;

/**
 * The custom-domain overlay's config gates (`config('teams.domains')`), mirroring
 * InvitationPolicy. Whether the overlay is on, and how much of the team-area
 * (self-service) surface teams get. A system admin can always manage any team's
 * domains from Admin › Teams while the overlay is enabled, whatever `team_access`
 * says. See ~dev/TEAMS_DOMAINS_SCOPE.md.
 */
final class DomainPolicy
{
    /** Is the custom-domain overlay active? */
    public static function enabled(): bool
    {
        return (bool) config('teams.domains.enabled', false);
    }

    /** Team-area surface: 'manage' | 'read-only' | 'none'. */
    public static function teamAccess(): string
    {
        return (string) config('teams.domains.team_access', 'manage');
    }

    /** May a team owner see their team's domains in the team area? */
    public static function teamCanView(): bool
    {
        return self::enabled() && in_array(self::teamAccess(), ['read-only', 'manage'], true);
    }

    /** May a team owner add/verify/remove their team's domains in the team area? */
    public static function teamCanManage(): bool
    {
        return self::enabled() && self::teamAccess() === 'manage';
    }
}
