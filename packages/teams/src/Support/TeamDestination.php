<?php

declare(strict_types=1);

namespace Concise\Teams\Support;

use App\Models\User;

/**
 * Where a non-admin user belongs right now, resolved purely from membership —
 * their only team if they have exactly one, the picker when they have several,
 * onboarding when they have none (OQ1). Stateless by design: there is no stored
 * "current team" (see HasTeams), so this writes nothing — which also makes it
 * safe to ask "where would this user land?" while impersonating. Shared by
 * {@see TeamRedirect} (the `/{prefix}` front door) and the post-login resolver
 * on App\Support\Auth\LoginRedirectRegistry, so a member reaches their team in
 * one hop after login instead of bouncing through the front door.
 *
 * "Resume my last team" for a multi-team user is intentionally not here: that's
 * a per-device preference (cookie/session), not shared user state, and isn't
 * built yet — a multi-team user picks each session.
 */
final class TeamDestination
{
    public static function resolve(User $user): string
    {
        $teams = $user->accessibleTeams()->orderBy('name')->get();

        return match (true) {
            $teams->isEmpty() => route('team.onboarding'),
            $teams->count() === 1 => team_route('team.dashboard', $teams->first()),
            default => route('team.select'),
        };
    }
}
