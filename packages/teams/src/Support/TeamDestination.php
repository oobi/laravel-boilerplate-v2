<?php

declare(strict_types=1);

namespace Concise\Teams\Support;

use App\Models\User;
use Concise\Teams\Http\Controllers\TeamRedirect;

/**
 * Where a non-admin user belongs right now: their current team if they can
 * still enter it, their only team if they have exactly one (switching them
 * into it), the picker when they have several, or onboarding when they have
 * none — "first alphabetically" was a guess, and picking for someone is
 * confusing when their current team was deactivated or they were suspended
 * from it (OQ1 for the zero-team case). Shared by {@see TeamRedirect}
 * (the `/{prefix}` front door) and the post-login resolver registered on
 * App\Support\Auth\LoginRedirectRegistry, so a team member reaches their team
 * in one hop after login instead of bouncing through the front door.
 */
final class TeamDestination
{
    public static function resolve(User $user): string
    {
        $current = $user->currentTeam;

        if ($current !== null && $current->active && $user->belongsToTeam($current)) {
            return team_route('team.dashboard', $current);
        }

        $teams = $user->accessibleTeams()->orderBy('name')->get();

        if ($teams->isEmpty()) {
            return route('team.onboarding');
        }

        if ($teams->count() === 1) {
            $team = $teams->first();
            $user->switchTeam($team);

            return team_route('team.dashboard', $team);
        }

        return route('team.select');
    }
}
