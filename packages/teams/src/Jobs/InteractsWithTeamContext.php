<?php

declare(strict_types=1);

namespace Concise\Teams\Jobs;

use Concise\Teams\Jobs\Middleware\ApplyTeamContext;
use Concise\Teams\Models\Team;
use Concise\Teams\Support\TeamContext;

/**
 * Makes a queued job carry its team scope explicitly and re-establish it when it
 * runs, instead of trusting ambient context to survive serialization — the queue
 * being the classic cross-team leakage vector (~dev/TEAMS_TIER_SCOPE.md §5.5).
 *
 * Capture the scope at dispatch (in the job's constructor):
 *
 *     public function __construct(public Report $report)
 *     {
 *         $this->captureTeamContext();
 *     }
 *
 * The bundled job middleware then restores that scope around handle(). A job that
 * defines its own middleware() should include `new ApplyTeamContext`.
 */
trait InteractsWithTeamContext
{
    public int|string|null $teamContextId = null;

    /** Capture the current team as this job's scope. Call from the constructor. */
    public function captureTeamContext(): static
    {
        $this->teamContextId = app(TeamContext::class)->id();

        return $this;
    }

    /** Explicitly set this job's team scope. */
    public function forTeamContext(Team|int|string|null $team): static
    {
        $this->teamContextId = $team instanceof Team ? $team->getKey() : $team;

        return $this;
    }

    /** @return array<int, object> */
    public function middleware(): array
    {
        return [new ApplyTeamContext];
    }
}
