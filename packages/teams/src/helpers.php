<?php

declare(strict_types=1);

use Concise\Teams\Models\Team;
use Concise\Teams\Support\TeamCache;
use Concise\Teams\Support\TeamContext;
use Concise\Teams\Support\TeamLabels;
use Concise\Teams\Support\TeamStorage;
use Illuminate\Contracts\Filesystem\Filesystem;

if (! function_exists('team_trans')) {
    /**
     * A teams-tier string (lang/en/teams.php, key without the `teams::teams.`
     * prefix) with the project's word for a team already supplied as
     * `:team`/`:Team`/`:teams`/`:Teams` — see TeamLabels.
     *
     * @param  array<string, mixed>  $replace
     */
    function team_trans(string $key, array $replace = []): string
    {
        return TeamLabels::trans($key, $replace);
    }
}

if (! function_exists('team_trans_choice')) {
    /**
     * The pluralising counterpart of team_trans().
     *
     * @param  array<string, mixed>  $replace
     */
    function team_trans_choice(string $key, int $number, array $replace = []): string
    {
        return TeamLabels::transChoice($key, $number, $replace);
    }
}

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
