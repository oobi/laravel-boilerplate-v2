<?php

namespace Tests\Feature\Teams;

use App\Enums\SystemPermission;
use App\Models\User;
use Concise\Teams\Livewire\Admin\UserMemberships;
use Concise\Teams\Models\Team;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Managing a user's existing team memberships from the admin User show page, in bulk or by row (GitHub #72). */
class BulkUserMembershipsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Team $north;

    private Team $south;

    private Team $owned;

    private Team $coOwned;

    protected function setUp(): void
    {
        parent::setUp();

        Team::createRole('Member');
        Team::createRole('Editor');

        $this->user = User::factory()->create();
        $this->north = Team::factory()->create(['name' => 'Northwind']);
        $this->north->addMember($this->user, 'Member');
        $this->south = Team::factory()->create(['name' => 'Southwind']);
        $this->south->addMember($this->user, 'Member');
        $this->owned = Team::factory()->ownedBy($this->user)->create(['name' => 'Owned Co']);
        $this->coOwned = Team::factory()->create(['name' => 'Co-owned Co']);
        $this->coOwned->addMember($this->user, 'Member');
        $this->coOwned->makeOwner($this->user);
    }

    public function test_roles_change_in_every_selected_team(): void
    {
        $this->page()
            ->selectTableRecords([$this->north, $this->south])
            ->callAction(TestAction::make('bulkChangeRoles')->table()->bulk(), data: ['roles' => 'Editor'])
            ->assertHasNoFormErrors()
            ->assertNotified();

        $this->assertSame('Editor', $this->north->fresh()->roleFor($this->user->fresh()));
        $this->assertSame('Editor', $this->south->fresh()->roleFor($this->user->fresh()));
    }

    /** Membership is memoised on the User instance, so each check asks a fresh one. */
    public function test_memberships_are_suspended_then_reinstated(): void
    {
        $this->page()
            ->selectTableRecords([$this->north, $this->south])
            ->callAction(TestAction::make('bulkSuspend')->table()->bulk());

        $this->assertTrue($this->north->fresh()->isSuspended($this->user->fresh()));
        $this->assertTrue($this->south->fresh()->isSuspended($this->user->fresh()));

        $this->page()
            ->selectTableRecords([$this->north])
            ->callAction(TestAction::make('bulkReinstate')->table()->bulk())
            ->assertNotified(team_trans_choice('memberships.reinstated_in', 1, ['person' => $this->user->name, 'count' => 1]));

        $this->assertFalse($this->north->fresh()->isSuspended($this->user->fresh()));
        $this->assertTrue($this->south->fresh()->isSuspended($this->user->fresh()));
    }

    public function test_the_user_is_removed_from_the_selected_teams_but_never_one_they_own_or_co_own(): void
    {
        // Owned teams can't be ticked in the browser; a forged selection is still skipped.
        $this->page()
            ->selectTableRecords([$this->north, $this->owned, $this->coOwned])
            ->callAction(TestAction::make('bulkRemove')->table()->bulk());

        $this->assertFalse($this->north->fresh()->hasUser($this->user->fresh()));
        $this->assertTrue($this->south->fresh()->hasUser($this->user->fresh()));
        $this->assertTrue($this->owned->fresh()->hasUser($this->user->fresh()));
        $this->assertTrue($this->coOwned->fresh()->hasUser($this->user->fresh()));
    }

    /** The selection is re-read through the table's query, which only lists the user's current teams. */
    public function test_a_membership_ended_elsewhere_while_the_page_was_open_is_skipped(): void
    {
        $page = $this->page()
            ->selectTableRecords([$this->north, $this->south])
            ->mountAction(TestAction::make('bulkSuspend')->table()->bulk());

        $this->north->removeMember($this->user);

        $page->callMountedAction()
            ->assertNotified(team_trans_choice('memberships.suspended_in', 1, ['person' => $this->user->name, 'count' => 1]));

        $this->assertTrue($this->south->fresh()->isSuspended($this->user->fresh()));
    }

    public function test_reinstating_teams_where_nobody_is_suspended_changes_nothing_and_says_so(): void
    {
        $this->page()
            ->selectTableRecords([$this->north])
            ->callAction(TestAction::make('bulkReinstate')->table()->bulk())
            ->assertNotified(team_trans('memberships.nothing_to_change'));
    }

    public function test_a_row_can_be_changed_on_its_own_and_owned_teams_have_no_row_actions(): void
    {
        $this->page()
            ->mountAction(TestAction::make('changeRole')->table($this->north))
            ->assertSchemaStateSet(['roles' => 'Member'])
            ->assertMountedActionModalSee($this->user->name.' in Northwind.')
            ->fillForm(['roles' => 'Editor'])
            ->callMountedAction()
            ->callAction(TestAction::make('suspend')->table($this->south))
            ->assertActionHidden(TestAction::make('remove')->table($this->owned))
            ->assertActionHidden(TestAction::make('remove')->table($this->coOwned));

        $this->assertSame('Editor', $this->north->fresh()->roleFor($this->user->fresh()));
        $this->assertTrue($this->south->fresh()->isSuspended($this->user->fresh()));

        $this->page()
            ->callAction(TestAction::make('reinstate')->table($this->south))
            ->callAction(TestAction::make('remove')->table($this->north));

        $this->assertFalse($this->south->fresh()->isSuspended($this->user->fresh()));
        $this->assertFalse($this->north->fresh()->hasUser($this->user->fresh()));
    }

    public function test_none_of_it_shows_without_manage_teams(): void
    {
        $viewer = User::factory()->withPermission(SystemPermission::VIEW_USERS)->create();

        Livewire::actingAs($viewer)
            ->test(UserMemberships::class, ['user' => $this->user])
            ->assertActionHidden(TestAction::make('bulkRemove')->table()->bulk())
            ->assertActionHidden(TestAction::make('remove')->table($this->north));
    }

    private function page(): mixed
    {
        return Livewire::actingAs(User::factory()->superAdmin()->create())
            ->test(UserMemberships::class, ['user' => $this->user]);
    }
}
