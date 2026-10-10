<?php

namespace Tests\Feature\Teams;

use App\Enums\SystemPermission;
use App\Models\User;
use Concise\Teams\Enums\TeamPermission;
use Concise\Teams\Livewire\Admin\UserMemberships;
use Concise\Teams\Models\Team;
use Concise\Teams\Support\TeamContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    public function test_each_row_shows_its_standing_without_a_query_per_team(): void
    {
        Team::createRole('Team Admin', [TeamPermission::MANAGE_MEMBERS]);
        $user = User::factory()->create();
        $this->actingAs(User::factory()->superAdmin()->create());

        $queriesFor = function () use ($user): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            Livewire::test(UserMemberships::class, ['user' => $user]);

            return count(DB::getQueryLog());
        };

        $owned = Team::factory()->ownedBy($user)->create(['name' => 'Owned Co']);
        $coOwned = Team::factory()->create(['name' => 'Co Owned']);
        $coOwned->addMember($user);
        $coOwned->makeOwner($user);
        $admin = Team::factory()->create(['name' => 'Admin Co']);
        $admin->addMember($user, 'Team Admin');
        $suspended = Team::factory()->create(['name' => 'Paused Co']);
        $suspended->addMember($user, 'Team Admin');
        $suspended->suspendMember($user);
        $queriesFor(); // Warms the permission cache, which the first check loads.
        $few = $queriesFor();

        foreach (range(1, 6) as $index) {
            Team::factory()->create()->addMember($user, 'Team Admin');
        }

        $this->assertSame($few, $queriesFor(), 'more teams, same queries');

        // Each row on its own: the badges a page shows elsewhere can't satisfy another row's check.
        Livewire::test(UserMemberships::class, ['user' => $user])
            ->assertTableColumnStateSet('standing', [team_trans('members.primary_owner')], $owned)
            ->assertTableColumnStateSet('standing', [team_trans('members.owner')], $coOwned)
            ->assertTableColumnStateSet('standing', ['Team Admin'], $admin)
            ->assertTableColumnStateSet('standing', ['Team Admin', team_trans('members.suspended')], $suspended);
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
