<?php

namespace Tests\Feature\Teams;

use App\Models\User;
use Concise\Teams\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** A deactivated team keeps its members but is not enterable until reactivated. */
class TeamActiveStateTest extends TestCase
{
    use RefreshDatabase;

    public function test_members_cannot_enter_an_inactive_team(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->ownedBy($user)->inactive()->create();

        $this->actingAs($user)
            ->get(route('team.dashboard', ['team' => $team->slug]))
            ->assertForbidden();

        $this->assertTrue($team->hasUser($user), 'membership survives deactivation');
    }

    public function test_the_entry_point_skips_an_inactive_current_team(): void
    {
        $user = User::factory()->create();
        $inactive = Team::factory()->ownedBy($user)->inactive()->create();
        $active = Team::factory()->ownedBy($user)->create();
        $user->switchTeam($inactive);

        $this->actingAs($user)
            ->get(route('team.index'))
            ->assertRedirect(route('team.dashboard', ['team' => $active->slug]));
    }

    public function test_the_entry_point_sends_a_user_with_only_inactive_teams_to_onboarding(): void
    {
        $user = User::factory()->create();
        Team::factory()->ownedBy($user)->inactive()->create();

        $this->actingAs($user)
            ->get(route('team.index'))
            ->assertRedirect(route('team.onboarding'));
    }

    public function test_the_switcher_does_not_list_inactive_teams(): void
    {
        $user = User::factory()->create();
        $active = Team::factory()->ownedBy($user)->create(['name' => 'Active Co']);
        Team::factory()->ownedBy($user)->inactive()->create(['name' => 'Dormant Co']);
        Team::factory()->ownedBy($user)->create(['name' => 'Other Co']);

        $this->actingAs($user)
            ->get(route('team.dashboard', ['team' => $active->slug]))
            ->assertOk()
            ->assertSee('Other Co')
            ->assertDontSee('Dormant Co');
    }
}
