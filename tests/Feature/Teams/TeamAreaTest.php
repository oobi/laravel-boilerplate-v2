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

    public function test_the_breadcrumb_uses_the_team_context_not_the_admin_root(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['name' => 'Northwind', 'slug' => 'northwind']);
        $team->users()->attach($user);

        $response = $this->actingAs($user)
            ->get(route('team.dashboard', ['team' => $team->slug]))
            ->assertOk()
            ->assertSee('Northwind');

        // Strip the app name first: one like "Salon Admin" contains the root label legitimately.
        $html = str_replace(e(config('app.name')), '', $response->getContent());

        $this->assertStringNotContainsString(e(__('admin.breadcrumb_root')), $html);
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

    public function test_the_index_redirects_a_single_team_member_to_their_team(): void
    {
        $user = User::factory()->create();
        $team = $this->teamWithMember($user);

        $this->actingAs($user)
            ->get(route('team.index'))
            ->assertRedirect(route('team.dashboard', ['team' => $team->slug]));
    }

    public function test_the_index_sends_a_system_user_to_the_admin_dashboard(): void
    {
        // The team entry point is the admin front door: a system user belongs on
        // the dashboard, not a team area (they enter a team via the picker).
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->get(route('team.index'))
            ->assertRedirect(route('dashboard'));
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
