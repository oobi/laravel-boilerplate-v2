<?php

declare(strict_types=1);

namespace Concise\Teams\Jobs\Middleware;

use Closure;
use Concise\Teams\Models\Team;
use Concise\Teams\Support\TeamContext;
use Spatie\Permission\PermissionRegistrar;

/**
 * Job middleware that re-establishes the team scope a job captured at dispatch
 * (see InteractsWithTeamContext) for the duration of handle(), then restores the
 * previous scope. Without this, a job would run under the worker's ambient scope
 * — which is exactly the leakage the teams tier prevents.
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

        if ($team !== null) {
            return app(TeamContext::class)->run($team, fn () => $next($job));
        }

        // Team no longer exists: still pin the permission scope to its id so
        // nothing resolves against the system (or another team) scope.
        $registrar = app(PermissionRegistrar::class);
        $previous = $registrar->getPermissionsTeamId();
        $registrar->setPermissionsTeamId($teamId);

        try {
            return $next($job);
        } finally {
            $registrar->setPermissionsTeamId($previous);
        }
    }
}
