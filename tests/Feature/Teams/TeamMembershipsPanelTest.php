<?php

namespace Tests\Feature\Teams;

use App\Enums\SystemPermission;
use App\Models\User;
use Concise\Teams\Enums\TeamPermission;
use Concise\Teams\Livewire\Admin\UserMemberships;
use Concise\Teams\Models\Team;
use Concise\Teams\Support\TeamContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** The tier's panel on the admin User show page, registered through PanelRegistry. */
class TeamMembershipsPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_user_show_page_lists_team_memberships_with_roles(): void
    {
        Team::createRole('Team Admin', [TeamPermission::MANAGE_MEMBERS]);
        $user = User::factory()->create();
        Team::factory()->ownedBy($user)->create(['name' => 'Owned Co']);
        Team::factory()->create(['name' => 'Other Co'])->addMember($user, 'Team Admin');

        $this->actingAs(User::factory()->superAdmin()->create())
            ->get(route('users.show', $user))
            ->assertOk()
            ->assertSee('Team memberships')
            ->assertSee('Owned Co')
            ->assertSee('Owner')
            ->assertSee('Other Co')
            ->assertSee('Team Admin')
            ->assertSee('Member since');
    }

    public function test_an_inactive_team_is_flagged(): void
    {
        $user = User::factory()->create();
        Team::factory()->create(['name' => 'Closed Co', 'active' => false])->addMember($user);

        $this->actingAs(User::factory()->superAdmin()->create());

        Livewire::test(UserMemberships::class, ['user' => $user])
            ->assertSee('Closed Co')
            ->assertSee('Inactive');
    }

    public function test_a_user_in_no_teams_shows_the_empty_state(): void
    {
        $user = User::factory()->create();

        $this->actingAs(User::factory()->superAdmin()->create())
            ->get(route('users.show', $user))
            ->assertOk()
            ->assertSee('Team memberships')
            ->assertSee('Does not belong to any team.');
    }

    public function test_memberships_are_paginated(): void
    {
        $user = User::factory()->create();
        $teams = Team::factory()->count(25)->sequence(fn ($sequence): array => ['name' => sprintf('Team %02d', $sequence->index)])->create();
        $teams->each(fn (Team $team) => $team->addMember($user));

        $this->actingAs(User::factory()->superAdmin()->create());

        Livewire::test(UserMemberships::class, ['user' => $user])
            ->assertCanSeeTableRecords($teams->take(20))
            ->assertCanNotSeeTableRecords($teams->slice(20))
            ->call('gotoPage', 2)
            ->assertCanSeeTableRecords($teams->slice(20))
            ->assertCanNotSeeTableRecords($teams->take(20));
    }

    public function test_the_memberships_list_requires_the_view_users_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs(User::factory()->withPermission(SystemPermission::VIEW_TEAMS)->create());

        Livewire::test(UserMemberships::class, ['user' => $user])->assertForbidden();
    }

    protected function tearDown(): void
    {
        app(TeamContext::class)->clear();

        parent::tearDown();
    }
}
