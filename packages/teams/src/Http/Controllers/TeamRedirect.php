<?php

declare(strict_types=1);

namespace Concise\Teams\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The `/{prefix}` entry point: sends a user somewhere sensible rather than
 * guessing. Their current team if they can still enter it, their only team if
 * they have exactly one, otherwise the select panel — "first alphabetically"
 * was a guess, and picking for someone is confusing when their current team
 * was deactivated or they were suspended from it. No teams at all goes to
 * onboarding (OQ1).
 */
class TeamRedirect
{
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();
        $current = $user->currentTeam;

        if ($current !== null && $current->active && $user->belongsToTeam($current)) {
            return redirect()->to(team_route('team.dashboard', $current));
        }

        $teams = $user->accessibleTeams()->orderBy('name')->get();

        if ($teams->isEmpty()) {
            return redirect()->route('team.onboarding');
        }

        if ($teams->count() === 1) {
            $team = $teams->first();
            $user->switchTeam($team);

            return redirect()->to(team_route('team.dashboard', $team));
        }

        return redirect()->route('team.select');
    }
}
