<?php

declare(strict_types=1);

namespace Concise\Teams\Http\Controllers;

use Concise\Teams\Support\TeamDestination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The `/{prefix}` entry point: sends a user somewhere sensible rather than
 * guessing. A system user goes to the admin dashboard (to *enter* a team, an
 * admin uses the picker — the account menu links there); everyone else via
 * {@see TeamDestination} (also reused by the post-login resolver, so a team
 * member reaches their team without bouncing through this page first).
 */
class TeamRedirect
{
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->canAccessAdmin()) {
            return redirect()->route('dashboard');
        }

        return redirect()->to(TeamDestination::resolve($user));
    }
}
