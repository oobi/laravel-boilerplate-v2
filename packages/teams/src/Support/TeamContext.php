<?php

declare(strict_types=1);

namespace Concise\Teams\Support;

use Concise\Teams\Exceptions\TeamContextMissing;
use Concise\Teams\Models\Team;
use Illuminate\Contracts\Filesystem\Filesystem;

/**
 * The single source of truth for "which team are we acting as" (see
 * ~dev/TEAMS_TIER_SCOPE.md §5.5): the scope for team-confined filesystem and
 * cache access, and for any query scoping a project adds. Bound as a
 * singleton; reached via the CurrentTeam facade.
 *
 * Permissions are NOT scoped by this context. A member's team role is read
 * from the membership pivot (Team::memberHasPermission), and system roles are
 * stock spatie with no team feature — so a SystemPermission answers the same
 * inside a team route as outside, with nothing to pin.
 *
 * Fail-loud: the scoped disk()/cache() throw when no team is set rather than
 * quietly using unscoped storage.
 */
class TeamContext
{
    protected ?Team $team = null;

    /** Make the given team the active scope. */
    public function set(Team $team): void
    {
        $this->team = $team;
    }

    /** Clear the team scope. */
    public function clear(): void
    {
        $this->team = null;
    }

    public function get(): ?Team
    {
        return $this->team;
    }

    public function has(): bool
    {
        return $this->team !== null;
    }

    public function getOrFail(): Team
    {
        return $this->team ?? throw TeamContextMissing::make();
    }

    public function id(): int|string|null
    {
        return $this->team?->getKey();
    }

    /** Run a callback with the given team as the active scope, then restore. */
    public function run(Team $team, callable $callback): mixed
    {
        $previous = $this->team;

        $this->set($team);

        try {
            return $callback();
        } finally {
            $previous === null ? $this->clear() : $this->set($previous);
        }
    }

    /** A filesystem confined to the current team's storage area (fail-loud). */
    public function disk(): Filesystem
    {
        return TeamStorage::for($this->getOrFail());
    }

    /** A cache repository namespaced to the current team (fail-loud). */
    public function cache(): TeamCache
    {
        return TeamCache::for($this->getOrFail());
    }
}
