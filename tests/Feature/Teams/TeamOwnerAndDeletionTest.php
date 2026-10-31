<?php

namespace Tests\Feature\Teams;

use App\Models\User;
use App\Support\UserDeletionGuards;
use Concise\Teams\Livewire\Admin\Teams\ListTeams;
use Concise\Teams\Models\Team;
use Concise\Teams\Models\TeamInvitation;
use Filament\Actions\Testing\TestAction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Deleted teams and owners no longer cause 500s (GitHub oobi#28): a team's
 * primary owner can't be deleted until the team is transferred or deleted, a
 * deleted team takes its pending invitations with it, and anything left from
 * before still renders.
 */
class TeamOwnerAndDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_primary_owner_cannot_be_deleted_and_is_told_why(): void
    {
        $owner = User::factory()->create(['first_name' => 'Jordan', 'last_name' => 'Lee']);
        Team::factory()->ownedBy($owner)->create(['name' => 'Acme']);
        Team::factory()->ownedBy($owner)->create(['name' => 'Beta']);
        $this->actingAs(User::factory()->superAdmin()->create());

        $reason = team_trans_choice('ownership.owner_cannot_be_deleted', 2, ['person' => 'Jordan Lee', 'names' => 'Acme and Beta']);
        $this->assertThrows(fn () => $owner->delete(), AuthorizationException::class, $reason);
        $this->assertNotSoftDeleted($owner);
    }

    public function test_a_member_who_owns_no_team_can_be_deleted(): void
    {
        $member = User::factory()->create();
        Team::factory()->create()->addMember($member);

        $member->delete();

        $this->assertSoftDeleted($member);
    }

    public function test_a_deleted_team_blocks_only_a_permanent_delete_of_its_owner(): void
    {
        $owner = User::factory()->create();
        Team::factory()->ownedBy($owner)->create()->delete();

        $owner->delete();
        $this->assertSoftDeleted($owner);

        $this->assertThrows(fn () => $owner->forceDelete(), AuthorizationException::class);
        $this->assertModelExists($owner);
    }

    public function test_the_owner_of_only_deleted_teams_is_told_to_purge_them(): void
    {
        $owner = User::factory()->create(['first_name' => 'Jordan', 'last_name' => 'Lee']);
        Team::factory()->ownedBy($owner)->create(['name' => 'Acme'])->delete();

        $this->assertSame(
            team_trans_choice('ownership.owner_of_deleted_teams', 1, ['person' => 'Jordan Lee', 'names' => 'Acme']),
            UserDeletionGuards::reasonFor($owner, permanently: true),
        );
    }

    public function test_the_database_refuses_to_remove_a_teams_owner(): void
    {
        $owner = User::factory()->create();
        Team::factory()->ownedBy($owner)->create();

        $this->assertThrows(fn () => User::withoutEvents(fn () => $owner->forceDelete()), QueryException::class);
    }

    public function test_a_team_cannot_be_restored_while_its_owner_is_deleted(): void
    {
        $owner = User::factory()->create(['first_name' => 'Jordan', 'last_name' => 'Lee']);
        $team = Team::factory()->ownedBy($owner)->create(['name' => 'Acme']);
        $team->delete();
        $owner->delete();

        $reason = team_trans('admin.restore_owner_deleted', ['person' => 'Jordan Lee', 'name' => 'Acme']);
        $this->assertThrows(fn () => $team->restore(), AuthorizationException::class, $reason);
        $this->assertSoftDeleted($team);

        $action = Livewire::actingAs(User::factory()->superAdmin()->create())
            ->test(ListTeams::class)
            ->mountAction(TestAction::make('restore')->table($team))
            ->instance()
            ->getMountedAction();
        $this->assertSame($reason, $action->getModalDescription());
        $this->assertNull($action->getModalSubmitAction());

        $owner->restore();
        $team->restore();
        $this->assertNotSoftDeleted($team);
    }

    public function test_deleting_a_team_removes_its_pending_invitations(): void
    {
        $team = Team::factory()->create();
        $invitation = TeamInvitation::factory()->create(['team_id' => $team->id]);

        $team->delete();

        $this->assertModelMissing($invitation);
    }

    /** An invitation left from before teams took theirs with them: a 404, not a 500. */
    public function test_an_invitation_link_to_a_deleted_team_is_not_found(): void
    {
        $team = Team::factory()->create();
        $invitee = User::factory()->create(['email' => 'invitee@example.com']);
        $invitation = TeamInvitation::factory()->create(['team_id' => $team->id, 'email' => 'invitee@example.com']);
        $acceptUrl = $invitation->acceptUrl();
        $registerUrl = $invitation->registerUrl();
        Team::withoutEvents(fn () => $team->delete());

        $this->get($registerUrl)->assertNotFound();
        $this->actingAs($invitee)->get($acceptUrl)->assertNotFound();
    }

    /** A team whose owner was deleted before that was refused still opens. */
    public function test_team_settings_open_when_the_owner_was_deleted(): void
    {
        $owner = User::factory()->create(['first_name' => 'Jordan', 'last_name' => 'Lee']);
        $team = Team::factory()->ownedBy($owner)->create();
        User::withoutEvents(fn () => $owner->delete());

        $this->actingAs(User::factory()->superAdmin()->create())
            ->get(route('teams.settings', $team))
            ->assertOk()
            ->assertSee('Jordan Lee');
    }
}
