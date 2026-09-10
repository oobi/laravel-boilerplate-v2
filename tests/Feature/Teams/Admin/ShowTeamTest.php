<?php

namespace Tests\Feature\Teams\Admin;

use App\Models\User;
use Concise\Teams\Enums\TeamPermission;
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

    public function test_the_overview_shows_details_statistics_and_the_tabs(): void
    {
        $this->team->addMember(User::factory()->create(), 'Member');
        TeamInvitation::factory()->count(3)->create(['team_id' => $this->team->id]);

        $this->actingAs($this->admin)
            ->get(route('teams.show', $this->team))
            ->assertOk()
            ->assertSee('Northwind')
            ->assertSee('Olive Owner')
            ->assertSee(route('teams.members', $this->team))
            ->assertSee(route('teams.invitations', $this->team))
            ->assertSee(route('teams.settings', $this->team))
            ->assertSeeInOrder([__('Members'), '2'])
            ->assertSeeInOrder([__('Pending invitations'), '3']);
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
            ->assertCanSeeTableRecords([$admin])
            ->assertCanNotSeeTableRecords([$this->owner, $member, $suspended])
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
