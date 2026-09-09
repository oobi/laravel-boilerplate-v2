<?php

namespace Tests\Feature\Teams;

use Concise\Teams\Facades\CurrentTeam;
use Concise\Teams\Jobs\InteractsWithTeamContext;
use Concise\Teams\Models\Team;
use Concise\Teams\Support\TeamContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/** A queued job re-establishes the team scope it captured at dispatch (§5.5). */
class RecordsTeamContextJob implements ShouldQueue
{
    use Dispatchable, InteractsWithTeamContext;

    public static int|string|null $seenTeamId = null;

    public function __construct()
    {
        $this->captureTeamContext();
    }

    public function handle(): void
    {
        self::$seenTeamId = CurrentTeam::id();
    }
}

class TeamJobContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_job_reestablishes_its_captured_team_scope(): void
    {
        $team = Team::factory()->create();

        // The sync queue runs jobs through the same CallQueuedHandler path a
        // worker uses, so job middleware (ApplyTeamContext) actually fires.
        config(['queue.default' => 'sync']);

        CurrentTeam::set($team);
        $job = new RecordsTeamContextJob;      // captures team at construction
        CurrentTeam::clear();                   // worker starts with no ambient scope

        // Round-trip through serialization the way a real queue would.
        $job = unserialize(serialize($job));

        dispatch($job);

        $this->assertSame($team->id, RecordsTeamContextJob::$seenTeamId);
        // Scope is restored afterwards, not left pointing at the job's team.
        $this->assertFalse(CurrentTeam::has());
        $this->assertSame(0, app(PermissionRegistrar::class)->getPermissionsTeamId());
    }

    protected function tearDown(): void
    {
        RecordsTeamContextJob::$seenTeamId = null;
        app(TeamContext::class)->clear();

        parent::tearDown();
    }
}
