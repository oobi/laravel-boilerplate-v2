<?php

declare(strict_types=1);

namespace Concise\Teams\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The `/{prefix}` entry point: sends a user to a sensible team. Resolves the
 * user's current team (or their first), persists it as current, and redirects to
 * its dashboard; a user with no team is sent to onboarding (OQ1). A many-team
 * picker can layer on later — current-team + switcher already covers it.
 */
class TeamRedirect
{
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();

        $team = $user->currentTeam ?? $user->teams()->first();

        if ($team === null) {
            return redirect()->route('team.onboarding');
        }

        if ($user->current_team_id !== $team->getKey()) {
            $user->switchTeam($team);
        }

        return redirect()->route('team.dashboard', ['team' => $team->slug]);
    }
}
