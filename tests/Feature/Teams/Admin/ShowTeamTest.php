<?php

namespace Tests\Feature\Teams\Admin;

use App\Enums\SystemPermission;
use App\Models\User;
use Concise\Teams\Enums\TeamPermission;
use Concise\Teams\Livewire\Admin\Teams\ShowTeam;
use Concise\Teams\Livewire\Admin\Teams\TeamDomains;
use Concise\Teams\Livewire\Admin\Teams\TeamSettings;
use Concise\Teams\Livewire\Team\MembersTable;
use Concise\Teams\Models\Team;
use Concise\Teams\Models\TeamInvitation;
use Concise\Teams\Support\TeamContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** The admin team pages: Overview and Members tabs (Settings and Invitations have their own tests). */
class ShowTeamTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $owner;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        Team::createRole('Team Admin', [TeamPermission::MANAGE_MEMBERS]);
        Team::createRole('Member');

        $this->admin = User::factory()->superAdmin()->create();
        $this->owner = User::factory()->create(['first_name' => 'Olive', 'last_name' => 'Owner']);
        $this->team = Team::factory()->ownedBy($this->owner)->create(['name' => 'Northwind']);
    }

    public function test_non_managers_are_forbidden_from_every_tab(): void
    {
        $support = User::factory()->support()->create();

        foreach (['teams.show', 'teams.members', 'teams.invitations', 'teams.settings'] as $route) {
            $this->actingAs($support)->get(route($route, $this->team))->assertForbidden();
        }
    }

    public function test_view_teams_opens_every_tab_read_only(): void
    {
        $viewer = User::factory()->withPermission(SystemPermission::VIEW_TEAMS)->create();
        $member = User::factory()->create(['first_name' => 'Mia', 'last_name' => 'Member']);
        $this->team->addMember($member, 'Member');

        foreach (['teams.show', 'teams.members', 'teams.settings'] as $route) {
            $this->actingAs($viewer)->get(route($route, $this->team))->assertOk();
        }

        Livewire::actingAs($viewer)
            ->test(MembersTable::class, ['team' => $this->team])
            ->assertSee('Mia Member')
            ->assertActionHidden('addMember')
            ->assertTableActionHidden('changeRole', $member)
            ->assertTableActionHidden('remove', $member);
    }

    public function test_the_overview_shows_details_statistics_and_the_tabs(): void
    {
        $this->team->addMember(User::factory()->create(), 'Member');
        TeamInvitation::factory()->count(3)->create(['team_id' => $this->team->id]);

        $this->actingAs($this->admin)
            ->get(route('teams.show', $this->team))
            ->assertOk()
            ->assertSee('Northwind')
            ->assertSee('Olive Owner')
            ->assertSee(team_route('team.dashboard', $this->team))
            ->assertSee(route('teams.members', $this->team))
            ->assertSee(route('teams.invitations', $this->team))
            ->assertSee(route('teams.settings', $this->team))
            ->assertSeeInOrder([__('Members'), '2'])
            ->assertSeeInOrder([__('Pending invitations'), '3']);
    }

    public function test_opening_a_private_team_offers_to_impersonate_its_owner(): void
    {
        // The admin isn't a member; opening the team's URL offers the way in:
        // impersonate the owner (in the action's own CSRF-protected request) and
        // land in *this* team, not the owner's generic post-login home.
        $this->assertFalse($this->team->hasUser($this->admin));

        Livewire::actingAs($this->admin)
            ->test(ShowTeam::class, ['team' => $this->team])
            ->callAction('openTeam')
            ->assertRedirect(team_route('team.dashboard', $this->team));

        $this->assertAuthenticatedAs($this->owner);
    }

    public function test_a_viewer_who_cannot_impersonate_is_not_offered_it(): void
    {
        $viewer = User::factory()->withPermission(SystemPermission::VIEW_TEAMS)->create();

        Livewire::actingAs($viewer)
            ->test(ShowTeam::class, ['team' => $this->team])
            ->callAction('openTeam')
            ->assertNoRedirect();
    }

    public function test_the_domains_tab_404s_when_the_tier_is_off(): void
    {
        // The route is always registered, but the tab is inert unless custom domains are on.
        Livewire::actingAs($this->admin)
            ->test(TeamDomains::class, ['team' => $this->team])
            ->assertNotFound();
    }

    public function test_a_system_admin_opens_the_domains_tab_when_custom_domains_are_on(): void
    {
        config(['teams.domains.enabled' => true, 'teams.domains.custom_domains' => true]);

        Livewire::actingAs($this->admin)
            ->test(TeamDomains::class, ['team' => $this->team])
            ->assertOk()
            ->assertSee(team_trans('domains.title'));
    }

    public function test_the_settings_tab_moves_domains_out_to_their_own_tab(): void
    {
        config(['teams.domains.enabled' => true, 'teams.domains.custom_domains' => true]);

        Livewire::actingAs($this->admin)
            ->test(TeamSettings::class, ['team' => $this->team])
            // The Domains tab is offered…
            ->assertSee(route('teams.domains', $this->team))
            // …but the section no longer lives inside Settings.
            ->assertDontSee(team_trans('domains.description', ['name' => $this->team->name]));
    }

    public function test_the_admin_tabs_hide_domains_when_the_tier_is_off(): void
    {
        Livewire::actingAs($this->admin)
            ->test(TeamSettings::class, ['team' => $this->team])
            ->assertDontSee(route('teams.domains', $this->team));
    }

    public function test_the_members_tab_lists_members_with_their_standing(): void
    {
        $member = User::factory()->create(['first_name' => 'Mia', 'last_name' => 'Member']);
        $this->team->addMember($member, 'Team Admin');

        $this->actingAs($this->admin)
            ->get(route('teams.members', $this->team))
            ->assertOk()
            ->assertSee('Olive')
            ->assertSee('Primary Owner')
            ->assertSee('Mia')
            ->assertSee('Team Admin');
    }

    public function test_a_member_can_be_added_with_a_role(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($this->admin)
            ->test(MembersTable::class, ['team' => $this->team])
            ->callAction('addMember', data: ['user_id' => $user->id, 'roles' => 'Member'])
            ->assertHasNoActionErrors();

        $this->assertTrue($this->team->fresh()->hasUser($user));
        $this->assertSame('Member', $this->team->roleFor($user));
    }

    public function test_add_member_is_a_system_admin_tool_not_a_team_one(): void
    {
        Livewire::actingAs($this->owner)
            ->test(MembersTable::class, ['team' => $this->team])
            ->assertActionHidden('addMember');
    }

    public function test_a_system_admin_manages_members_without_being_one(): void
    {
        $member = User::factory()->create();
        $this->team->addMember($member, 'Member');
        $this->assertFalse($this->team->hasUser($this->admin));

        Livewire::actingAs($this->admin)
            ->test(MembersTable::class, ['team' => $this->team])
            ->assertTableActionHidden('remove', $this->owner)
            ->callTableAction('changeRole', $member, data: ['roles' => 'Team Admin'])
            ->assertHasNoTableActionErrors()
            ->callTableAction('remove', $member);

        $this->assertFalse($this->team->fresh()->hasUser($member));
    }

    public function test_members_can_be_searched_and_filtered(): void
    {
        $admin = User::factory()->create(['first_name' => 'Ada', 'last_name' => 'Admin']);
        $this->team->addMember($admin, 'Team Admin');
        $member = User::factory()->create(['first_name' => 'Mia', 'last_name' => 'Member']);
        $this->team->addMember($member, 'Member');
        $suspended = User::factory()->create(['first_name' => 'Sam', 'last_name' => 'Suspended']);
        $this->team->addMember($suspended, 'Member');
        $this->team->suspendMember($suspended);

        Livewire::actingAs($this->admin)
            ->test(MembersTable::class, ['team' => $this->team])
            ->assertCanSeeTableRecords([$this->owner, $admin, $member, $suspended])
            ->searchTable('Mia')
            ->assertCanSeeTableRecords([$member])
            ->assertCanNotSeeTableRecords([$this->owner, $admin, $suspended])
            ->searchTable('')
            ->filterTable('role', 'Team Admin')
            // The owner holds the default owner role (Team Admin) too, so they match.
            ->assertCanSeeTableRecords([$admin, $this->owner])
            ->assertCanNotSeeTableRecords([$member, $suspended])
            ->resetTableFilters()
            ->filterTable('role', MembersTable::OWNERS_FILTER_VALUE)
            ->assertCanSeeTableRecords([$this->owner])
            ->assertCanNotSeeTableRecords([$admin, $member, $suspended])
            ->resetTableFilters()
            ->filterTable('status', 'suspended')
            ->assertCanSeeTableRecords([$suspended])
            ->assertCanNotSeeTableRecords([$this->owner, $admin, $member])
            ->resetTableFilters()
            ->filterTable('status', 'active')
            ->assertCanSeeTableRecords([$this->owner, $admin, $member])
            ->assertCanNotSeeTableRecords([$suspended]);
    }

    public function test_members_are_paginated(): void
    {
        User::factory()->count(24)->create(['last_name' => 'Aardvark'])->each(fn (User $user) => $this->team->addMember($user));
        $last = User::factory()->create(['last_name' => 'Zebra']);
        $this->team->addMember($last);

        // 26 members, 20 per page: the last-sorted member is on page two until the page size grows.
        Livewire::actingAs($this->admin)
            ->test(MembersTable::class, ['team' => $this->team])
            ->assertSet('tableRecordsPerPage', 20)
            ->assertCountTableRecords(26)
            ->assertCanNotSeeTableRecords([$last])
            ->set('tableRecordsPerPage', 50)
            ->assertCanSeeTableRecords([$last]);
    }

    protected function tearDown(): void
    {
        app(TeamContext::class)->clear();

        parent::tearDown();
    }
}
