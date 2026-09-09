<?php

declare(strict_types=1);

use Concise\Teams\Models\Team;
use Concise\Teams\Support\TeamCache;
use Concise\Teams\Support\TeamContext;
use Concise\Teams\Support\TeamStorage;
use Illuminate\Contracts\Filesystem\Filesystem;

if (! function_exists('current_team')) {
    /** The active team, or null when in the system scope. */
    function current_team(): ?Team
    {
        return app(TeamContext::class)->get();
    }
}

if (! function_exists('team_disk')) {
    /**
     * A filesystem confined to a team's storage area. Defaults to the current
     * team; throws (fail-loud) when neither is available.
     */
    function team_disk(?Team $team = null): Filesystem
    {
        return TeamStorage::for($team ?? app(TeamContext::class)->getOrFail());
    }
}

if (! function_exists('team_cache')) {
    /**
     * A cache repository namespaced to a team. Defaults to the current team;
     * throws (fail-loud) when neither is available.
     */
    function team_cache(?Team $team = null): TeamCache
    {
        return TeamCache::for($team ?? app(TeamContext::class)->getOrFail());
    }
}
