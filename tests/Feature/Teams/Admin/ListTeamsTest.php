<?php

namespace Tests\Feature\Teams\Admin;

use App\Enums\SystemPermission;
use App\Models\User;
use Concise\Teams\Livewire\Admin\Teams\ListTeams;
use Concise\Teams\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ListTeamsTest extends TestCase
{
    use RefreshDatabase;

    /** A non-super-admin holding the `manage teams` system permission. */
    private function teamManager(): User
    {
        return User::factory()
            ->withPermission(SystemPermission::ACCESS_ADMIN_PANEL, SystemPermission::MANAGE_TEAMS)
            ->create();
    }

    public function test_admin_users_without_the_manage_teams_permission_are_forbidden(): void
    {
        $this->actingAs(User::factory()->support()->create())
            ->get(route('teams.index'))
            ->assertForbidden();
    }

    public function test_a_role_holding_manage_teams_can_view_the_list(): void
    {
        $team = Team::factory()->create(['name' => 'Northwind']);

        $this->actingAs($this->teamManager())
            ->get(route('teams.index'))
            ->assertOk()
            ->assertSee('Northwind');
    }

    public function test_the_list_shows_each_teams_owner_and_member_count(): void
    {
        $owner = User::factory()->create(['first_name' => 'Olive', 'last_name' => 'Owner']);
        $team = Team::factory()->ownedBy($owner)->create();
        $team->addMember(User::factory()->create());

        Livewire::actingAs(User::factory()->superAdmin()->create())
            ->test(ListTeams::class)
            ->assertCanSeeTableRecords([$team])
            ->assertSee($owner->name)
            ->assertTableColumnStateSet('users_count', 2, $team);
    }

    public function test_a_team_can_be_created_with_an_owner(): void
    {
        $owner = User::factory()->create();

        Livewire::actingAs(User::factory()->superAdmin()->create())
            ->test(ListTeams::class)
            ->callAction('createTeam', data: ['name' => 'Northwind', 'user_id' => $owner->id, 'active' => true])
            ->assertHasNoActionErrors();

        $team = Team::query()->where('name', 'Northwind')->firstOrFail();
        $this->assertTrue($team->isOwnedBy($owner));
        $this->assertTrue($team->hasUser($owner), 'the owner is the first member');
        $this->assertNotEmpty($team->slug);
    }

    public function test_a_team_can_be_deactivated_and_reactivated_from_the_list(): void
    {
        $team = Team::factory()->create();

        $component = Livewire::actingAs(User::factory()->superAdmin()->create())
            ->test(ListTeams::class)
            ->callTableAction('toggleActive', $team);

        $this->assertFalse($team->fresh()->active);

        $component->callTableAction('toggleActive', $team);

        $this->assertTrue($team->fresh()->active);
    }

    public function test_the_status_filter_narrows_to_active_or_inactive_teams(): void
    {
        $active = Team::factory()->create();
        $inactive = Team::factory()->inactive()->create();

        Livewire::actingAs(User::factory()->superAdmin()->create())
            ->test(ListTeams::class)
            ->assertCanSeeTableRecords([$active, $inactive])
            ->filterTable('active', '0')
            ->assertCanSeeTableRecords([$inactive])
            ->assertCanNotSeeTableRecords([$active])
            ->filterTable('active', '1')
            ->assertCanSeeTableRecords([$active])
            ->assertCanNotSeeTableRecords([$inactive]);
    }

    public function test_delete_is_only_offered_for_an_inactive_team(): void
    {
        $active = Team::factory()->create();
        $inactive = Team::factory()->inactive()->create();

        Livewire::actingAs(User::factory()->superAdmin()->create())
            ->test(ListTeams::class)
            ->assertTableActionHidden('delete', $active)
            ->assertTableActionVisible('delete', $inactive)
            ->callTableAction('delete', $inactive);

        $this->assertSoftDeleted('teams', ['id' => $inactive->id]);
        $this->assertNotSoftDeleted('teams', ['id' => $active->id]);
    }

    public function test_a_deleted_team_can_be_restored_from_the_trash_view(): void
    {
        $team = Team::factory()->inactive()->create();
        $team->delete();

        Livewire::actingAs(User::factory()->superAdmin()->create())
            ->test(ListTeams::class)
            ->assertCanNotSeeTableRecords([$team])
            ->filterTable('trashed', '0')
            ->assertCanSeeTableRecords([$team])
            ->callTableAction('restore', $team);

        $this->assertNotSoftDeleted('teams', ['id' => $team->id]);
    }

    public function test_force_deleting_a_team_erases_its_memberships_and_role_assignments(): void
    {
        Team::createRole('Member');
        $team = Team::factory()->inactive()->create();
        $member = User::factory()->create();
        $team->addMember($member, 'Member');
        $team->delete();

        Livewire::actingAs(User::factory()->superAdmin()->create())
            ->test(ListTeams::class)
            ->filterTable('trashed', '0')
            ->callTableAction('forceDelete', $team);

        $this->assertDatabaseMissing('teams', ['id' => $team->id]);
        $this->assertDatabaseMissing('team_user', ['team_id' => $team->id]);
        $this->assertDatabaseMissing(config('permission.table_names.model_has_roles'), ['team_id' => $team->id]);
        $this->assertTrue($member->fresh()->exists, 'the member keeps their account');
    }

    public function test_the_trash_can_be_emptied(): void
    {
        $team = Team::factory()->inactive()->create();
        $team->delete();
        $kept = Team::factory()->create();

        Livewire::actingAs(User::factory()->superAdmin()->create())
            ->test(ListTeams::class)
            ->callAction('emptyTrash');

        $this->assertDatabaseMissing('teams', ['id' => $team->id]);
        $this->assertModelExists($kept);
    }

    public function test_the_sidebar_links_to_teams_for_managers_only(): void
    {
        $this->actingAs($this->teamManager())
            ->get(route('dashboard'))
            ->assertSee(route('teams.index'));

        $this->actingAs(User::factory()->support()->create())
            ->get(route('dashboard'))
            ->assertDontSee(route('teams.index'));
    }
}
