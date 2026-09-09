<?php

namespace Tests\Feature\Teams;

use Concise\Teams\Exceptions\TeamContextMissing;
use Concise\Teams\Facades\CurrentTeam;
use Concise\Teams\Models\Team;
use Concise\Teams\Support\TeamContext;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Safety-critical: a team must never reach another team's files or cache, and
 * scoped primitives fail loud without a team (~dev/TEAMS_TIER_SCOPE.md §5.5).
 */
class TeamIsolationTest extends TestCase
{
    use RefreshDatabase;

    private string $diskRoot;

    protected function setUp(): void
    {
        parent::setUp();

        // A real (temp-rooted) base disk: the `scoped` driver rebuilds from the
        // inner disk's config, so Storage::fake() wouldn't reach it.
        $this->diskRoot = storage_path('framework/testing/teams_'.Str::random(8));
        config([
            'filesystems.disks.teams_base' => ['driver' => 'local', 'root' => $this->diskRoot, 'throw' => true],
            'teams.filesystem.disk' => 'teams_base',
        ]);
    }

    public function test_scoped_disk_throws_without_a_current_team(): void
    {
        $this->expectException(TeamContextMissing::class);

        team_disk();
    }

    public function test_a_team_cannot_read_another_teams_files(): void
    {
        $teamA = Team::factory()->create();
        $teamB = Team::factory()->create();

        team_disk($teamA)->put('secret.txt', 'team A only');

        $this->assertSame('team A only', team_disk($teamA)->get('secret.txt'));
        $this->assertFalse(team_disk($teamB)->exists('secret.txt'));
        $this->assertEmpty(team_disk($teamB)->allFiles());
    }

    public function test_scoped_disks_store_under_distinct_team_prefixes(): void
    {
        $teamA = Team::factory()->create();
        $teamB = Team::factory()->create();

        team_disk($teamA)->put('logo.png', 'a');
        team_disk($teamB)->put('logo.png', 'b');

        $base = Storage::disk('teams_base');
        $base->assertExists("teams/{$teamA->id}/logo.png");
        $base->assertExists("teams/{$teamB->id}/logo.png");
        $this->assertSame('a', $base->get("teams/{$teamA->id}/logo.png"));
    }

    public function test_a_team_cannot_read_another_teams_cache(): void
    {
        $teamA = Team::factory()->create();
        $teamB = Team::factory()->create();

        team_cache($teamA)->put('setting', 'value-a');

        $this->assertSame('value-a', team_cache($teamA)->get('setting'));
        $this->assertNull(team_cache($teamB)->get('setting'));
        $this->assertNotSame(
            team_cache($teamA)->key('setting'),
            team_cache($teamB)->key('setting'),
        );
    }

    public function test_setting_the_current_team_sets_the_permission_scope(): void
    {
        $team = Team::factory()->create();

        CurrentTeam::set($team);

        $this->assertTrue(CurrentTeam::has());
        $this->assertSame($team->id, CurrentTeam::id());
        $this->assertSame($team->id, app(PermissionRegistrar::class)->getPermissionsTeamId());
    }

    public function test_run_restores_the_previous_scope_afterwards(): void
    {
        $outer = Team::factory()->create();
        $inner = Team::factory()->create();

        CurrentTeam::set($outer);

        $seen = CurrentTeam::run($inner, function () {
            return CurrentTeam::id();
        });

        $this->assertSame($inner->id, $seen);
        $this->assertSame($outer->id, CurrentTeam::id());
    }

    protected function tearDown(): void
    {
        app(TeamContext::class)->clear();
        (new Filesystem)->deleteDirectory($this->diskRoot);

        parent::tearDown();
    }
}
