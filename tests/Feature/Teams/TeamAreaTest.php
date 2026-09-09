<?php

namespace Tests\Feature\Teams;

use App\Models\User;
use Concise\Teams\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamAreaTest extends TestCase
{
    use RefreshDatabase;

    private function teamWithMember(User $user): Team
    {
        $team = Team::factory()->create();
        $team->users()->attach($user);

        return $team;
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $team = Team::factory()->create();

        $this->get(route('team.dashboard', ['team' => $team->slug]))
            ->assertRedirect(route('login'));
    }

    public function test_a_member_can_view_the_team_dashboard(): void
    {
        $user = User::factory()->create();
        $team = $this->teamWithMember($user);

        $this->actingAs($user)
            ->get(route('team.dashboard', ['team' => $team->slug]))
            ->assertOk()
            ->assertSee($team->name);
    }

    public function test_a_non_member_is_forbidden_from_the_team_area(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(); // user is not attached

        $this->actingAs($user)
            ->get(route('team.dashboard', ['team' => $team->slug]))
            ->assertForbidden();
    }

    public function test_a_super_admin_who_is_not_a_member_is_still_forbidden(): void
    {
        // The team area is members-only; system admins manage teams from the
        // separate system area, not by entering a team they don't belong to.
        $admin = User::factory()->superAdmin()->create();
        $team = Team::factory()->create();

        $this->actingAs($admin)
            ->get(route('team.dashboard', ['team' => $team->slug]))
            ->assertForbidden();
    }

    public function test_the_index_redirects_a_member_to_their_current_team(): void
    {
        $user = User::factory()->create();
        $team = $this->teamWithMember($user);

        $this->actingAs($user)
            ->get(route('team.index'))
            ->assertRedirect(route('team.dashboard', ['team' => $team->slug]));

        $this->assertSame($team->id, $user->fresh()->current_team_id);
    }

    public function test_a_user_with_no_team_is_sent_to_onboarding(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('team.index'))
            ->assertRedirect(route('team.onboarding'));

        $this->actingAs($user)
            ->get(route('team.onboarding'))
            ->assertOk();
    }
}
