<?php

declare(strict_types=1);

namespace Concise\Teams\Support;

use Concise\Teams\Exceptions\TeamContextMissing;
use Concise\Teams\Models\Team;
use Illuminate\Contracts\Filesystem\Filesystem;
use Spatie\Permission\PermissionRegistrar;

/**
 * The single source of truth for "which team are we acting as" (see
 * ~dev/TEAMS_TIER_SCOPE.md §5.5). Setting the current team also sets spatie's
 * permissions team scope, so query/permission/filesystem/cache scoping never
 * drift apart. Bound as a singleton; reached via the CurrentTeam facade.
 *
 * Fail-loud: the scoped disk()/cache() throw when no team is set rather than
 * quietly using unscoped storage.
 */
class TeamContext
{
    protected ?Team $team = null;

    /** Make the given team the active scope (also sets spatie's team id). */
    public function set(Team $team): void
    {
        $this->team = $team;
        app(PermissionRegistrar::class)->setPermissionsTeamId($team->getKey());
    }

    /** Clear the team scope, returning to the system scope. */
    public function clear(): void
    {
        $this->team = null;
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
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
