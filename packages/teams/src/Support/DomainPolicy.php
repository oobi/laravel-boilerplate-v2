<?php

declare(strict_types=1);

namespace Concise\Teams\Support;

/**
 * The custom-domain overlay's feature flag. Whether it's active at all is config
 * (infra-tied: wildcard DNS/TLS); *who* may manage a team's domains is runtime
 * authorization — the MANAGE_DOMAINS team permission plus a sovereign owner's
 * bypass and system admins — not config. See ~dev/TEAMS_DOMAINS_SCOPE.md.
 */
final class DomainPolicy
{
    public static function enabled(): bool
    {
        return (bool) config('teams.domains.enabled', false);
    }
}
