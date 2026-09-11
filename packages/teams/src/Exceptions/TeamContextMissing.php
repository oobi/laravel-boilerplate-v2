<?php

declare(strict_types=1);

namespace Concise\Teams\Exceptions;

use RuntimeException;

/**
 * Thrown when a team-scoped primitive (disk, cache, ...) is used without a
 * resolved team. Isolation is fail-loud by design: a missing context surfaces
 * as an exception rather than silently falling back to unscoped storage, which
 * is exactly the cross-team leakage we are preventing (~dev/TEAMS_TIER_SCOPE.md §5.5).
 */
class TeamContextMissing extends RuntimeException
{
    public static function make(): self
    {
        return new self(
            'No current team is set. Team-scoped storage/cache require a resolved '
            .'team; call CurrentTeam::set()/run() or pass a team explicitly.'
        );
    }
}
