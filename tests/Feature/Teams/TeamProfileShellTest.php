<?php

namespace Tests\Feature\Teams;

use App\Models\User;
use Concise\Teams\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The profile opened from a team keeps that team's shell (the teams tier's
 * AccountAreaRegistry resolver), and drops it once the user could no longer
 * enter the team. The team switcher, titled with the team's name, marks the shell.
 */
class TeamProfileShellTest extends TestCase
{
    use RefreshDatabase;

    private function visitTeam(User $user, Team $team): void
    {
        $this->actingAs($user)->get(route('team.dashboard', ['team' => $team->slug]))->assertOk();
    }

    private function assertTeamShell(TestResponse $response, Team $team, bool $expected = true): void
    {
        $marker = 'title="'.e($team->name).'"';

        $expected
            ? $response->assertSee($marker, false)
            // Out of the team shell means into the sidebar-less one (its form-width column), not no shell at all.
            : $response->assertDontSee($marker, false)->assertSee('ui-page flex flex-col gap-4 max-w-3xl', false);
    }

    public function test_the_profile_keeps_the_team_shell_after_a_team_page(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->ownedBy($owner)->create();

        $this->visitTeam($owner, $team);

        $this->assertTeamShell($this->get(route('profile.edit'))->assertOk(), $team);
    }

    public function test_the_last_area_visited_wins(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $team = Team::factory()->ownedBy($admin)->create();

        $this->visitTeam($admin, $team);
        $this->get(route('dashboard'))->assertOk();

        $this->get(route('profile.edit'))
            ->assertOk()
            ->assertSee(route('users.index'))
            ->assertDontSee('title="'.e($team->name).'"', false);
    }

    public function test_the_profile_drops_the_team_shell_once_the_user_has_left(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $team = Team::factory()->ownedBy($owner)->create();
        $team->addMember($member);

        $this->visitTeam($member, $team);
        $team->removeMember($member);

        $this->assertTeamShell($this->get(route('profile.edit'))->assertOk(), $team, false);
    }

    public function test_the_profile_drops_the_team_shell_once_the_team_is_deactivated(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->ownedBy($owner)->create();

        $this->visitTeam($owner, $team);
        $team->update(['active' => false]);

        $this->assertTeamShell($this->get(route('profile.edit'))->assertOk(), $team, false);
    }

    public function test_the_profile_drops_the_team_shell_once_the_team_is_deleted(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->ownedBy($owner)->create();

        $this->visitTeam($owner, $team);
        $team->delete();

        $this->assertTeamShell($this->get(route('profile.edit'))->assertOk(), $team, false);
    }
}
