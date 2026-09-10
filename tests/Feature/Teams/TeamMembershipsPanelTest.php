<?php

namespace Tests\Feature\Teams;

use App\Models\User;
use Concise\Teams\Enums\TeamPermission;
use Concise\Teams\Models\Team;
use Concise\Teams\Support\TeamContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            ->assertSee(__('admin.team_memberships'))
            ->assertSee('Owned Co')
            ->assertSee('Owner')
            ->assertSee('Other Co')
            ->assertSee('Team Admin');
    }

    public function test_a_user_in_no_teams_shows_the_empty_state(): void
    {
        $user = User::factory()->create();

        $this->actingAs(User::factory()->superAdmin()->create())
            ->get(route('users.show', $user))
            ->assertOk()
            ->assertSee(__('admin.team_memberships'))
            ->assertSee('Not a member of any');
    }

    protected function tearDown(): void
    {
        app(TeamContext::class)->clear();

        parent::tearDown();
    }
}
