<?php

namespace Tests\Feature\Teams;

use App\Models\User;
use Concise\Teams\Enums\TeamPermission;
use Concise\Teams\Livewire\Team\PendingInvitations;
use Concise\Teams\Models\Team;
use Concise\Teams\Models\TeamInvitation;
use Concise\Teams\Notifications\TeamInvitationNotification;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Sending invitations is throttled per inviter and per address within a team
 * (GitHub oobi#27), so the invite form can't be used to flood an inbox.
 */
class TeamInvitationThrottleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Team::createRole('Team Admin', [TeamPermission::MANAGE_MEMBERS, TeamPermission::INVITE_MEMBERS]);
        Notification::fake();
    }

    private function invite(User $owner, Team $team, string $email): void
    {
        Livewire::actingAs($owner)
            ->test(PendingInvitations::class, ['team' => $team])
            ->callAction('invite', data: ['email' => $email, 'role' => null]);
    }

    public function test_a_team_can_send_an_address_only_so_many_invitations_an_hour(): void
    {
        config(['teams.invitations.throttle.per_address' => 2]);
        $owner = User::factory()->create();
        $team = Team::factory()->ownedBy($owner)->create();

        $this->invite($owner, $team, 'target@example.com');
        $this->invite($owner, $team, 'target@example.com');

        Livewire::actingAs($owner)
            ->test(PendingInvitations::class, ['team' => $team])
            ->callAction('invite', data: ['email' => 'target@example.com', 'role' => null])
            ->assertNotified(team_trans('invitations.throttled'));

        Notification::assertSentOnDemandTimes(TeamInvitationNotification::class, 2);
    }

    /** Counted per team, so a throwaway team can't use up an address's allowance and block a real one. */
    public function test_one_team_using_up_an_address_does_not_block_another_team(): void
    {
        config(['teams.invitations.throttle.per_address' => 1]);
        $teams = collect(range(1, 2))->map(fn (): Team => Team::factory()->ownedBy(User::factory()->create())->create());

        $this->invite($teams[0]->owner, $teams[0], 'target@example.com');
        $this->invite($teams[1]->owner, $teams[1], 'target@example.com');

        Notification::assertSentOnDemandTimes(TeamInvitationNotification::class, 2);
        $this->assertSame(1, $teams[1]->invitations()->count());
    }

    public function test_an_inviter_can_send_only_so_many_invitations_an_hour(): void
    {
        config(['teams.invitations.throttle.per_inviter' => 2]);
        $owner = User::factory()->create();
        $team = Team::factory()->ownedBy($owner)->create();

        $this->invite($owner, $team, 'one@example.com');
        $this->invite($owner, $team, 'two@example.com');

        Livewire::actingAs($owner)
            ->test(PendingInvitations::class, ['team' => $team])
            ->callAction('invite', data: ['email' => 'three@example.com', 'role' => null])
            ->assertNotified(team_trans('invitations.throttled'));

        Notification::assertSentOnDemandTimes(TeamInvitationNotification::class, 2);
        $this->assertDatabaseMissing('team_invitations', ['email' => 'three@example.com']);
    }

    public function test_resending_counts_towards_the_limits(): void
    {
        config(['teams.invitations.throttle.per_address' => 1]);
        $owner = User::factory()->create();
        $team = Team::factory()->ownedBy($owner)->create();
        $this->invite($owner, $team, 'target@example.com');
        $invitation = TeamInvitation::query()->where('email', 'target@example.com')->firstOrFail();

        Livewire::actingAs($owner)
            ->test(PendingInvitations::class, ['team' => $team])
            ->callAction(TestAction::make('resend')->table($invitation))
            ->assertNotified(team_trans('invitations.throttled'));

        Notification::assertSentOnDemandTimes(TeamInvitationNotification::class, 1);
    }

    public function test_a_limit_of_zero_still_allows_one_an_hour(): void
    {
        config(['teams.invitations.throttle.per_inviter' => 0]);
        $owner = User::factory()->create();
        $team = Team::factory()->ownedBy($owner)->create();

        $this->invite($owner, $team, 'one@example.com');
        $this->invite($owner, $team, 'two@example.com');

        Notification::assertSentOnDemandTimes(TeamInvitationNotification::class, 1);
    }

    public function test_the_limit_lifts_after_an_hour(): void
    {
        config(['teams.invitations.throttle.per_address' => 1]);
        $owner = User::factory()->create();
        $team = Team::factory()->ownedBy($owner)->create();
        $this->invite($owner, $team, 'target@example.com');

        $this->travel(61)->minutes();
        $this->invite($owner, $team, 'target@example.com');

        Notification::assertSentOnDemandTimes(TeamInvitationNotification::class, 2);
    }
}
