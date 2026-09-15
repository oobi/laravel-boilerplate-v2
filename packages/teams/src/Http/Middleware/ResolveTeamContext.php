<?php

declare(strict_types=1);

namespace Concise\Teams\Http\Middleware;

use Closure;
use Concise\Teams\Models\Team;
use Concise\Teams\Support\DomainPolicy;
use Concise\Teams\Support\TeamContext;
use Concise\Teams\Support\TeamHostResolver;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the active team, makes it the current scope (TeamContext::set), and
 * enforces membership. The team comes from the request host in host mode (the
 * domains overlay implies the team, no slug) and from the route's {team} slug
 * binding in path mode. The team area is members-only by design — system admins
 * manage teams from the separate system "manage all teams" area, not by entering
 * a team they don't belong to (mirrors Buzz's EnsureTeamMember). See
 * ~dev/TEAMS_TIER_SCOPE.md §6/§7 and ~dev/TEAMS_DOMAINS_SCOPE.md §4.
 */
class ResolveTeamContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $team = $this->resolveTeam($request);

        if (! $team instanceof Team) {
            abort(404);
        }

        $user = $request->user();

        if ($user === null || ! $user->belongsToTeam($team)) {
            abort(403);
        }

        // Deactivated by a system admin: membership survives, access doesn't. The
        // switcher and /{prefix} entry point already skip inactive teams, so this
        // is only reached by a direct URL — say why rather than a bare 403.
        if (! $team->active) {
            abort(403, team_trans('inactive'));
        }

        $context = app(TeamContext::class);
        $context->set($team);

        try {
            return $next($request);
        } finally {
            $context->clear();
        }
    }

    /**
     * Host mode (domains overlay on): the team is implied by the request host,
     * bound as the {teamHost} route-domain parameter. Path mode: the {team} slug
     * binding. Either may yield null → 404.
     */
    private function resolveTeam(Request $request): ?Team
    {
        $host = $request->route('teamHost');

        if (DomainPolicy::enabled() && is_string($host)) {
            return TeamHostResolver::resolve($host);
        }

        $team = $request->route('team');

        return $team instanceof Team ? $team : null;
    }
}
