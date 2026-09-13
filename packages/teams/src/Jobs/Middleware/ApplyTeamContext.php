<?php

declare(strict_types=1);

namespace Concise\Teams\Jobs\Middleware;

use Closure;
use Concise\Teams\Models\Team;
use Concise\Teams\Support\TeamContext;

/**
 * Job middleware that re-establishes the team scope a job captured at dispatch
 * (see InteractsWithTeamContext) for the duration of handle(), then restores the
 * previous scope. Without this, a job would run under the worker's ambient scope
 * — which is exactly the leakage the teams tier prevents.
 *
 * If the team no longer exists the job runs with no team set: the scoped
 * storage/cache primitives are fail-loud, so it throws rather than touching
 * another team's data. (Permissions aren't scoped by context at all — a team
 * role is read from the membership pivot — so there is nothing to pin.)
 */
class ApplyTeamContext
{
    public function handle(object $job, Closure $next): mixed
    {
        $teamId = $job->teamContextId ?? null;

        if ($teamId === null) {
            return $next($job);
        }

        $team = Team::find($teamId);

        if ($team === null) {
            return $next($job);
        }

        return app(TeamContext::class)->run($team, fn () => $next($job));
    }
}
