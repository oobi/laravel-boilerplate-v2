<?php

declare(strict_types=1);

namespace Concise\Teams\Exceptions;

use RuntimeException;

/**
 * Thrown at boot when the custom-domain overlay is enabled without the two hosts
 * it cannot work without. Host mode is a structural, setup-time decision (it
 * switches every team from path to host URLs), so a half-configured overlay must
 * fail loud immediately rather than mis-route at runtime (~dev/TEAMS_DOMAINS_SCOPE.md §5).
 */
class DomainsMisconfigured extends RuntimeException
{
    public static function missingHosts(): self
    {
        return new self(
            'teams.domains.enabled is on but admin_host and/or base are not set. '
            .'Set TEAMS_DOMAINS_ADMIN_HOST and TEAMS_DOMAINS_BASE, or turn the '
            .'overlay off (TEAMS_DOMAINS_ENABLED=false).'
        );
    }
}
