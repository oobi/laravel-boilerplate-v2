<?php

declare(strict_types=1);

namespace Concise\Teams\Http\Middleware;

use Closure;
use Concise\Teams\Models\Team;
use Concise\Teams\Support\TeamContext;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the team from the route's {team} slug binding, makes it the active
 * scope (TeamContext::set also sets the spatie permissions team id), and enforces
 * membership. The team area is members-only by design — system admins manage teams
 * from the separate system "manage all teams" area, not by entering a team they
 * don't belong to (mirrors Buzz's EnsureTeamMember). See ~dev/TEAMS_TIER_SCOPE.md §6/§7.
 */
class ResolveTeamContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $team = $request->route('team');

        if (! $team instanceof Team) {
            abort(404);
        }

        $user = $request->user();

        if ($user === null || ! $user->belongsToTeam($team)) {
            abort(403);
        }

        $context = app(TeamContext::class);
        $context->set($team);

        try {
            return $next($request);
        } finally {
            $context->clear();
        }
    }
}
