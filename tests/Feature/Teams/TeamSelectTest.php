<?php

namespace Tests\Feature\Teams;

use App\Models\User;
use Concise\Teams\Livewire\Team\SelectTeam;
use Concise\Teams\Models\Team;
use Concise\Teams\Support\TeamContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The team picker: where the entry point sends a user with nothing obvious to
 * open, and where the switcher's "All teams" / "Create" land.
 */
class TeamSelectTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_entry_point_shows_the_picker_when_there_is_no_obvious_team(): void
    {
        $user = User::factory()->create();
        Team::factory()->count(2)->create()->each(fn (Team $team) => $team->addMember($user));

        $this->actingAs($user)->get(route('team.index'))->assertRedirect(route('team.select'));
    }

    public function test_the_entry_point_skips_the_picker_for_a_current_or_single_team(): void
    {
        $user = User::factory()->create();
        $only = Team::factory()->ownedBy($user)->create();

        $this->actingAs($user)->get(route('team.index'))
            ->assertRedirect(route('team.dashboard', ['team' => $only->slug]));

        $second = Team::factory()->create();
        $second->addMember($user);
        $user->switchTeam($second);

        $this->actingAs($user)->get(route('team.index'))
            ->assertRedirect(route('team.dashboard', ['team' => $second->slug]));
    }

    public function test_the_picker_lists_every_team_the_user_can_enter_with_context(): void
    {
        $user = User::factory()->create();
        Team::factory()->ownedBy($user)->create(['name' => 'Owned Co']);
        // The factory gives Member Co its own owner, so adding this user makes two.
        Team::factory()->create(['name' => 'Member Co'])->addMember($user);

        $this->actingAs($user)->get(route('team.select'))
            ->assertOk()
            ->assertSee('Owned Co')
            ->assertSee('Member Co')
            ->assertSee('Owner')       // their standing in the team they own
            ->assertSee('1 member')    // Owned Co
            ->assertSee('2 members');  // Member Co
    }

    public function test_the_picker_hides_teams_the_user_cannot_enter(): void
    {
        $user = User::factory()->create();
        Team::factory()->ownedBy($user)->create(['name' => 'Open Co']);
        Team::factory()->inactive()->create(['name' => 'Dormant Co'])->addMember($user);
        $suspended = Team::factory()->create(['name' => 'Suspended Co']);
        $suspended->addMember($user);
        $suspended->suspendMember($user);

        $this->actingAs($user)->get(route('team.select'))
            ->assertOk()
            ->assertSee('Open Co')
            ->assertDontSee('Dormant Co')
            ->assertDontSee('Suspended Co');
    }

    /**
     * Privacy: the panel is about entering a team, so it lists membership, never
     * the platform. Not even a super admin sees someone else's team here — their
     * global gate bypass doesn't apply, because nothing here asks a gate.
     */
    public function test_the_picker_never_shows_a_team_the_user_does_not_belong_to(): void
    {
        $user = User::factory()->create();
        Team::factory()->ownedBy($user)->create(['name' => 'Mine Co']);
        Team::factory()->create(['name' => 'Other Co']);

        $admin = User::factory()->superAdmin()->create();
        Team::factory()->ownedBy($admin)->create(['name' => 'Admin Own Co']);

        $this->actingAs($user)->get(route('team.select'))
            ->assertOk()
            ->assertSee('Mine Co')
            ->assertDontSee('Other Co');

        $this->actingAs($admin)->get(route('team.select'))
            ->assertOk()
            ->assertSee('Admin Own Co')
            ->assertDontSee('Other Co');
    }

    public function test_the_picker_sends_a_user_with_no_teams_to_onboarding(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('team.select'))
            ->assertRedirect(route('team.onboarding'));
    }

    public function test_the_filter_narrows_the_panel_to_teams_the_user_owns(): void
    {
        $user = User::factory()->create();
        $owned = Team::factory()->ownedBy($user)->create(['name' => 'Owned Co']);
        $coOwned = Team::factory()->create(['name' => 'Co-owned Co']);
        $coOwned->addMember($user);
        $coOwned->makeOwner($user);
        $joined = Team::factory()->create(['name' => 'Joined Co']);
        $joined->addMember($user);

        Livewire::actingAs($user)
            ->test(SelectTeam::class)
            ->assertSee('Owned Co')
            ->assertSee('Co-owned Co')
            ->assertSee('Joined Co')
            ->set('filter', SelectTeam::FILTER_OWNED)
            ->assertSee('Owned Co')
            ->assertSee('Co-owned Co')   // a co-owner owns it too (Slack model)
            ->assertDontSee('Joined Co');

        $this->assertTrue($owned->isOwnedBy($user));
    }

    public function test_the_filter_is_hidden_when_it_would_change_nothing(): void
    {
        $user = User::factory()->create();
        Team::factory()->count(2)->ownedBy($user)->create();

        // Owns all of them.
        Livewire::actingAs($user)
            ->test(SelectTeam::class)
            ->assertDontSee('My teams');

        // Owns none of them.
        $other = User::factory()->create();
        Team::factory()->count(2)->create()->each(fn (Team $team) => $team->addMember($other));

        Livewire::actingAs($other)
            ->test(SelectTeam::class)
            ->assertDontSee('My teams');
    }

    public function test_the_filter_survives_a_refresh_as_a_query_parameter(): void
    {
        $user = User::factory()->create();
        Team::factory()->ownedBy($user)->create(['name' => 'Owned Co']);
        Team::factory()->create(['name' => 'Joined Co'])->addMember($user);

        $this->actingAs($user)
            ->get(route('team.select', ['filter' => SelectTeam::FILTER_OWNED]))
            ->assertOk()
            ->assertSee('Owned Co')
            ->assertDontSee('Joined Co');
    }

    public function test_the_create_query_parameter_opens_the_modal(): void
    {
        $user = User::factory()->create();
        Team::factory()->ownedBy($user)->create();

        Livewire::actingAs($user)
            ->withQueryParams(['create' => 1])
            ->test(SelectTeam::class)
            ->assertActionMounted('createTeam');
    }

    public function test_the_switcher_links_to_the_picker_and_to_creation(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->ownedBy($user)->create();
        Team::factory()->create()->addMember($user);

        $this->actingAs($user)
            ->get(route('team.dashboard', ['team' => $team->slug]))
            ->assertOk()
            ->assertSee(route('team.select'))
            ->assertSee(route('team.select', ['create' => 1]));
    }

    public function test_the_switcher_offers_no_creation_when_the_mode_forbids_it(): void
    {
        config(['teams.creation' => 'admin-provisioned']);
        $user = User::factory()->create();
        $team = Team::factory()->ownedBy($user)->create();

        $this->actingAs($user)
            ->get(route('team.dashboard', ['team' => $team->slug]))
            ->assertOk()
            ->assertDontSee(route('team.select', ['create' => 1]));
    }

    protected function tearDown(): void
    {
        app(TeamContext::class)->clear();

        parent::tearDown();
    }
}
