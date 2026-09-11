<?php

namespace Tests\Feature\Teams;

use App\Models\User;
use App\Support\AccountMenu\AccountMenuRegistry;
use Concise\Teams\Models\Team;
use Concise\Teams\Support\TeamContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Crossing between the system admin area and the team area: the account-menu
 * links the teams tier registers, and the sidebar-less lobby layout the
 * pre-team pages (select / onboarding) render in.
 */
class TeamNavigationTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<int, string> */
    private function menuNamesFor(User $user): array
    {
        return AccountMenuRegistry::visible($user)->map(fn ($item): string => $item->name)->all();
    }

    public function test_an_admin_sees_both_cross_area_links(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->assertEqualsCanonicalizing(['teams', 'admin-dashboard'], $this->menuNamesFor($admin));
    }

    public function test_a_plain_member_sees_teams_but_not_the_admin_link(): void
    {
        $user = User::factory()->create();
        Team::factory()->ownedBy($user)->create();

        $this->assertSame(['teams'], $this->menuNamesFor($user));
    }

    public function test_a_user_with_no_teams_who_cannot_create_sees_neither(): void
    {
        config(['teams.creation' => 'admin-only']);
        $user = User::factory()->create();

        $this->assertSame([], $this->menuNamesFor($user));
    }

    public function test_the_teams_link_points_at_the_entry_point_and_follows_the_label(): void
    {
        config(['teams.labels.team.singular' => 'Salon', 'teams.labels.team.plural' => 'Salons']);
        $user = User::factory()->create();
        Team::factory()->ownedBy($user)->create();

        $teams = AccountMenuRegistry::visible($user)->firstWhere('name', 'teams');

        $this->assertSame(route('team.index'), $teams->getUrl());
        // "My Salons" — distinct from the admin sidebar's "Salons" (manage all).
        $this->assertSame('My Salons', $teams->getLabel());
    }

    public function test_the_admin_link_appears_in_the_header_for_an_admin_only(): void
    {
        // A team member without system access does not see the admin link on their team page.
        $member = User::factory()->create();
        $team = Team::factory()->ownedBy($member)->create();

        $this->actingAs($member)
            ->get(route('team.dashboard', ['team' => $team->slug]))
            ->assertOk()
            ->assertDontSee('Admin dashboard');

        // An admin who belongs to the team does.
        $admin = User::factory()->superAdmin()->create();
        $team->addMember($admin);

        $this->actingAs($admin)
            ->get(route('team.dashboard', ['team' => $team->slug]))
            ->assertOk()
            ->assertSee('Admin dashboard')
            ->assertSee(route('dashboard'));
    }

    public function test_the_select_lobby_has_no_admin_sidebar_but_keeps_the_header(): void
    {
        $admin = User::factory()->superAdmin()->create();
        Team::factory()->count(2)->create()->each(fn (Team $team) => $team->addMember($admin));

        $this->actingAs($admin)
            ->get(route('team.select'))
            ->assertOk()
            // The lobby layout: none of the admin sidebar's links, even for a super admin.
            ->assertDontSee(route('users.index'))
            ->assertDontSee(route('roles.index'))
            // …but the shared header is still there (profile + logout).
            ->assertSee(route('profile.edit'))
            ->assertSee(route('logout'))
            // …and the content is width-capped rather than spread across the whole viewport.
            ->assertSee('max-w-6xl');
    }

    public function test_the_onboarding_lobby_has_no_admin_sidebar(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->get(route('team.onboarding'))
            ->assertOk()
            ->assertDontSee(route('users.index'))
            ->assertSee(route('logout'));
    }

    protected function tearDown(): void
    {
        app(TeamContext::class)->clear();

        parent::tearDown();
    }
}
