<?php

namespace Tests\Feature\Teams;

use App\Enums\SystemPermission;
use App\Models\User;
use Concise\Teams\Actions\AddUserToTeams;
use Concise\Teams\Enums\TeamSystemPermission;
use Concise\Teams\Livewire\Admin\TeamPickerTable;
use Concise\Teams\Livewire\Admin\UserMemberships;
use Concise\Teams\Models\Team;
use Filament\Forms\Components\TableSelect\Livewire\TableSelectLivewireComponent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** "Add to teams" on the admin User show page: one user into several teams at once (GitHub #70). */
class AddUserToTeamsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Team::createRole('Member');
    }

    public function test_an_admin_adds_a_user_to_several_teams_with_one_role(): void
    {
        $user = User::factory()->create();
        $north = Team::factory()->create(['name' => 'Northwind']);
        $south = Team::factory()->create(['name' => 'Southwind']);
        $untouched = Team::factory()->create(['name' => 'Eastwind']);

        Livewire::actingAs(User::factory()->superAdmin()->create())
            ->test(UserMemberships::class, ['user' => $user])
            ->callAction('addToTeams', data: ['teams' => [$north->id, $south->id], 'roles' => 'Member'])
            ->assertHasNoFormErrors()
            ->assertNotified()
            ->assertSee('Northwind')
            ->assertSee('Southwind');

        $this->assertSame('Member', $north->fresh()->roleFor($user));
        $this->assertSame('Member', $south->fresh()->roleFor($user));
        $this->assertFalse($untouched->fresh()->hasUser($user));
    }

    public function test_the_picker_lists_teams_the_user_is_not_in_active_ones_first(): void
    {
        $user = User::factory()->create();
        $current = Team::factory()->create();
        $current->addMember($user);
        $open = Team::factory()->create();
        $inactive = Team::factory()->create(['active' => false]);

        $picker = Livewire::actingAs(User::factory()->superAdmin()->create())
            ->test(TableSelectLivewireComponent::class, [
                'tableConfiguration' => base64_encode(TeamPickerTable::class),
                'tableArguments' => ['user' => $user->id],
                'state' => [],
            ]);

        $picker->assertCanSeeTableRecords([$open])
            ->assertCanNotSeeTableRecords([$current, $inactive])
            ->filterTable('active', '0')
            ->assertCanSeeTableRecords([$inactive])
            ->assertCanNotSeeTableRecords([$current, $open]);
    }

    public function test_it_is_hidden_without_manage_teams(): void
    {
        $viewer = User::factory()->withPermission(SystemPermission::VIEW_USERS)->create();

        Livewire::actingAs($viewer)
            ->test(UserMemberships::class, ['user' => User::factory()->create()])
            ->assertActionHidden('addToTeams');
    }

    public function test_losing_manage_teams_with_the_page_open_refuses_the_add(): void
    {
        $admin = User::factory()->withPermission(SystemPermission::VIEW_USERS, TeamSystemPermission::MANAGE_TEAMS)->create();
        $user = User::factory()->create();
        $team = Team::factory()->create();

        // The form is open (mounted) and filled while the admin may manage teams; the permission goes before they press Add.
        $page = Livewire::actingAs($admin)
            ->test(UserMemberships::class, ['user' => $user])
            ->mountAction('addToTeams')
            ->fillForm(['teams' => [$team->id], 'roles' => 'Member']);

        $admin->revokePermissionTo(TeamSystemPermission::MANAGE_TEAMS->value);
        $admin->forgetCachedPermissions();

        $page->callMountedAction();

        $this->assertFalse($team->fresh()->hasUser($user));
    }

    public function test_the_form_refuses_ids_that_are_not_live_teams(): void
    {
        $trashed = Team::factory()->create();
        $trashed->delete();

        Livewire::actingAs(User::factory()->superAdmin()->create())
            ->test(UserMemberships::class, ['user' => User::factory()->create()])
            ->callAction('addToTeams', data: ['teams' => [$trashed->id, 999999], 'roles' => 'Member'])
            ->assertHasFormErrors(['teams']);

        // A forged request: ids that aren't whole numbers fail validation rather than reaching the query.
        Livewire::actingAs(User::factory()->superAdmin()->create())
            ->test(UserMemberships::class, ['user' => User::factory()->create()])
            ->callAction('addToTeams', data: ['teams' => ['abc', ['nested']], 'roles' => 'Member'])
            ->assertHasFormErrors(['teams']);
    }

    public function test_teams_already_joined_and_ids_that_are_not_live_teams_are_skipped(): void
    {
        $user = User::factory()->create();
        $current = Team::factory()->create();
        $current->addMember($user);
        $new = Team::factory()->create();
        $trashed = Team::factory()->create();
        $trashed->delete();

        $added = app(AddUserToTeams::class)($user, [$current->id, $new->id, $trashed->id, 999999], 'Member');

        $this->assertSame([$new->id], $added->modelKeys());
        $this->assertNull($current->fresh()->roleFor($user), 'an existing membership is left as it was');
        $this->assertFalse(Team::withTrashed()->find($trashed->id)->hasUser($user));
    }
}
