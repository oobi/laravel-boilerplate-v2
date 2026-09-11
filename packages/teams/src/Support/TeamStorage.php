<?php

declare(strict_types=1);

namespace Concise\Teams\Support;

use Concise\Teams\Models\Team;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * Returns a filesystem confined to a single team's storage area, using Laravel's
 * native `scoped` disk driver to prefix every path with `teams/{team_id}`. Because
 * the confinement is at the disk root, a team literally cannot address another
 * team's files — there is no path to escape to (~dev/TEAMS_TIER_SCOPE.md §5.5).
 */
class TeamStorage
{
    public static function for(Team $team): Filesystem
    {
        return Storage::build([
            'driver' => 'scoped',
            'disk' => config('teams.filesystem.disk'),
            'prefix' => config('teams.filesystem.prefix', 'teams').'/'.$team->getKey(),
        ]);
    }
}
