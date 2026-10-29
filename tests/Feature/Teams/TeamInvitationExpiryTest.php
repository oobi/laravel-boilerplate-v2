<?php

namespace Tests\Feature\Teams;

use App\Models\User;
use Concise\Teams\Actions\AcceptInvitation;
use Concise\Teams\Actions\InviteMember;
use Concise\Teams\Enums\TeamPermission;
use Concise\Teams\Livewire\Team\PendingInvitations;
use Concise\Teams\Models\Team;
use Concise\Teams\Models\TeamInvitation;
use Concise\Teams\Notifications\TeamInvitationNotification;
use Filament\Actions\Testing\TestAction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Invitation links expire (GitHub oobi#27): a link also lets its holder sign up
 * while public registration is closed, so a leaked one must not work forever.
 */
class TeamInvitationExpiryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Team::createRole('Team Admin', [TeamPermission::MANAGE_MEMBERS, TeamPermission::INVITE_MEMBERS]);
        Team::createRole('Member');
    }

    private function team(?User $owner = null): Team
    {
        return Team::factory()->ownedBy($owner ?? User::factory()->create())->create();
    }

    public function test_an_invitation_expires_after_the_configured_number_of_days(): void
    {
        Notification::fake();
        $this->freezeSecond();
        config(['teams.invitations.expires_after_days' => 30]);
        $owner = User::factory()->create();
        $team = $this->team($owner);

        $invitation = app(InviteMember::class)($team, 'invitee@example.com', null, $owner);

        $this->assertTrue($invitation->expires_at->equalTo(now()->addDays(30)));
    }

    public function test_an_expired_link_shows_the_expired_page_and_joins_nobody(): void
    {
        $team = $this->team();
        $invitee = User::factory()->create(['email' => 'invitee@example.com']);
        $invitation = TeamInvitation::factory()->create(['team_id' => $team->id, 'email' => 'invitee@example.com']);
        $link = $invitation->acceptUrl();

        $this->travel(TeamInvitation::lifetimeInDays() + 1)->days();

        $this->actingAs($invitee)
            ->get($link)
            ->assertStatus(410)
            ->assertSee(team_trans('invitations.expired.title'))
            ->assertSee($team->name);

        $this->assertFalse($team->fresh()->hasUser($invitee));
    }

    public function test_an_expired_link_cannot_register_an_account(): void
    {
        $team = $this->team();
        $invitation = TeamInvitation::factory()->create(['team_id' => $team->id, 'email' => 'newcomer@example.com']);
        $registerLink = $invitation->registerUrl();
        $storeLink = $invitation->registerUrl(store: true);

        $this->travel(TeamInvitation::lifetimeInDays() + 1)->days();

        $this->get($registerLink)->assertStatus(410);
        $this->post($storeLink, [
            'first_name' => 'New',
            'last_name' => 'Comer',
            'password' => 'Str0ng-passw0rd!',
            'password_confirmation' => 'Str0ng-passw0rd!',
        ])->assertStatus(410);

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'newcomer@example.com']);
    }

    /** Links sent before expiry existed carry no expiry of their own; the invitation's still applies. */
    public function test_a_link_without_its_own_expiry_still_follows_the_invitations(): void
    {
        $team = $this->team();
        $invitee = User::factory()->create(['email' => 'invitee@example.com']);
        $invitation = TeamInvitation::factory()->expired()->create(['team_id' => $team->id, 'email' => 'invitee@example.com']);

        $this->actingAs($invitee)
            ->get(URL::signedRoute('team.invitations.accept', ['invitation' => $invitation]))
            ->assertStatus(410);

        $this->assertFalse($team->fresh()->hasUser($invitee));
    }

    public function test_a_tampered_link_is_still_forbidden_rather_than_reported_expired(): void
    {
        $invitation = TeamInvitation::factory()->expired()->create(['team_id' => $this->team()->id]);

        $this->get($invitation->acceptUrl().'x')->assertForbidden();
    }

    public function test_accepting_an_expired_invitation_is_refused_by_the_action_too(): void
    {
        $team = $this->team();
        $invitee = User::factory()->create(['email' => 'invitee@example.com']);
        $invitation = TeamInvitation::factory()->expired()->create(['team_id' => $team->id, 'email' => 'invitee@example.com']);

        $this->assertThrows(fn () => app(AcceptInvitation::class)($invitation, $invitee), AuthorizationException::class);
        $this->assertFalse($team->fresh()->hasUser($invitee));
    }

    public function test_resending_starts_the_clock_again_but_the_earlier_link_still_expires(): void
    {
        Notification::fake();
        $this->freezeSecond();
        $owner = User::factory()->create();
        $team = $this->team($owner);
        $invitee = User::factory()->create(['email' => 'invitee@example.com']);
        $invitation = TeamInvitation::factory()->create(['team_id' => $team->id, 'email' => 'invitee@example.com']);
        $firstLink = $invitation->acceptUrl();

        $this->travel(TeamInvitation::lifetimeInDays() - 1)->days();

        Livewire::actingAs($owner)
            ->test(PendingInvitations::class, ['team' => $team])
            ->callAction(TestAction::make('resend')->table($invitation));

        $this->assertTrue($invitation->fresh()->expires_at->equalTo(now()->addDays(TeamInvitation::lifetimeInDays())));
        Notification::assertSentOnDemand(TeamInvitationNotification::class);

        $this->travel(2)->days();

        $this->actingAs($invitee)->get($firstLink)->assertStatus(410);
        $this->actingAs($invitee)->get($invitation->fresh()->acceptUrl())->assertRedirect();
        $this->assertTrue($team->fresh()->hasUser($invitee));
    }

    /** Resend goes through InviteMember, so a member can't resend a role they couldn't offer. */
    public function test_resend_applies_the_same_role_rules_as_inviting(): void
    {
        Notification::fake();
        Team::createRole('Boss', [TeamPermission::MANAGE_MEMBERS, TeamPermission::INVITE_MEMBERS, TeamPermission::UPDATE_TEAM]);
        $team = $this->team();
        $inviter = User::factory()->create();
        $team->addMember($inviter, 'Team Admin');
        $invitation = TeamInvitation::factory()->create(['team_id' => $team->id, 'role' => 'Boss']);

        Livewire::actingAs($inviter)
            ->test(PendingInvitations::class, ['team' => $team])
            ->callAction(TestAction::make('resend')->table($invitation))
            ->assertNotified(team_trans('invitations.role_not_allowed', ['role' => 'Boss']));

        Notification::assertNothingSent();
    }

    public function test_the_email_says_when_the_invitation_expires(): void
    {
        $invitation = TeamInvitation::factory()->create(['team_id' => $this->team()->id]);

        $mail = (new TeamInvitationNotification($invitation))->toMail(new AnonymousNotifiable);

        $this->assertStringContainsString($invitation->expires_at->toFormattedDateString(), implode(' ', $mail->outroLines));
    }

    public function test_the_pending_list_shows_the_expiry_date_and_marks_expired_invitations(): void
    {
        $owner = User::factory()->create();
        $team = $this->team($owner);
        $current = TeamInvitation::factory()->create(['team_id' => $team->id]);
        $expired = TeamInvitation::factory()->expired()->create(['team_id' => $team->id]);

        Livewire::actingAs($owner)
            ->test(PendingInvitations::class, ['team' => $team])
            ->assertTableColumnFormattedStateSet('expires_at', $current->expires_at->toFormattedDateString(), $current)
            ->assertTableColumnFormattedStateSet('expires_at', team_trans('invitations.expired_badge'), $expired);
    }

    public function test_invitations_are_pruned_a_while_after_they_expire(): void
    {
        $team = $this->team();
        $current = TeamInvitation::factory()->create(['team_id' => $team->id]);
        $recentlyExpired = TeamInvitation::factory()->create(['team_id' => $team->id, 'expires_at' => now()->subDays(TeamInvitation::PRUNE_AFTER_DAYS - 1)]);
        $longExpired = TeamInvitation::factory()->create(['team_id' => $team->id, 'expires_at' => now()->subDays(TeamInvitation::PRUNE_AFTER_DAYS + 1)]);

        $this->artisan('model:prune', ['--model' => [TeamInvitation::class]])->assertSuccessful();

        $this->assertModelExists($current);
        $this->assertModelExists($recentlyExpired);
        $this->assertModelMissing($longExpired);
    }

    public function test_pruning_is_scheduled_daily(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event): bool => str_contains((string) $event->command, 'model:prune') && str_contains((string) $event->command, 'TeamInvitation'));

        $this->assertNotNull($event, 'model:prune for TeamInvitation is scheduled');
        $this->assertSame('0 0 * * *', $event->expression);
    }
}
