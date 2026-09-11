<?php

namespace Tests\Feature\Teams;

use App\Models\User;
use Concise\Teams\Database\Seeders\Demo\TeamSeeder;
use Concise\Teams\Enums\TeamPermission;
use Concise\Teams\Models\Team;
use Concise\Teams\Support\TeamContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The demo seeder is the unit under test here, not fixture setup (tests.md). */
class TeamDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_teams_with_an_owner_and_members_across_the_available_roles(): void
    {
        Team::createRole('Team Admin', [TeamPermission::MANAGE_MEMBERS]);
        Team::createRole('Member');

        (new TeamSeeder)->run();

        $teams = Team::query()->with(['owner', 'users'])->get();
        $this->assertCount(5, $teams);

        foreach ($teams as $team) {
            $this->assertNotEmpty($team->slug);
            $this->assertTrue($team->hasUser($team->owner));
            $this->assertNull($team->roleFor($team->owner), 'ownership is structural, not a role');

            $members = $team->users->reject(fn (User $user) => $user->is($team->owner));
            $this->assertGreaterThanOrEqual(5, $members->count());

            $held = $members->map(fn (User $member) => $team->roleFor($member))->unique()->sort()->values()->all();
            $this->assertSame(['Member', 'Team Admin'], $held, 'members are spread across the roles');
        }

        // R3: users belong to several teams, with a role resolved per team.
        $this->assertGreaterThan(0, User::query()->has('teams', '>=', 2)->count());
    }

    public function test_it_still_seeds_membership_when_no_team_roles_exist(): void
    {
        (new TeamSeeder)->run();

        $team = Team::query()->with('users')->firstOrFail();
        $this->assertCount(6, $team->users, 'owner + five members');
        $this->assertNull($team->roleFor($team->users->last()));
    }

    protected function tearDown(): void
    {
        app(TeamContext::class)->clear();

        parent::tearDown();
    }
}
