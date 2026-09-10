<?php

namespace Tests\Feature\Teams;

use App\Models\User;
use Concise\Teams\Actions\InviteMember;
use Concise\Teams\Enums\TeamPermission;
use Concise\Teams\Livewire\Team\ListInvitations;
use Concise\Teams\Livewire\Team\PendingInvitations;
use Concise\Teams\Models\Team;
use Concise\Teams\Models\TeamInvitation;
use Concise\Teams\Notifications\TeamInvitationNotification;
use Concise\Teams\Support\TeamContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class TeamInvitationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Team::createRole('Team Admin', [TeamPermission::MANAGE_MEMBERS, TeamPermission::INVITE_MEMBERS]);
        Team::createRole('Wrangler', [TeamPermission::MANAGE_MEMBERS]); // can manage, can't invite
        Team::createRole('Member');
    }

    private function team(User $owner): Team
    {
        return Team::factory()->ownedBy($owner)->create();
    }

    public function test_the_owner_can_invite_someone_by_email(): void
    {
        Notification::fake();
        $owner = User::factory()->create();
        $team = $this->team($owner);

        Livewire::actingAs($owner)
            ->test(ListInvitations::class, ['team' => $team])
            ->callAction('invite', data: ['email' => 'New.Person@Example.com', 'role' => 'Member'])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('team_invitations', ['team_id' => $team->id, 'email' => 'new.person@example.com', 'role' => 'Member']);
        Notification::assertSentOnDemand(
            TeamInvitationNotification::class,
            fn (TeamInvitationNotification $notification, array $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routes['mail'] === 'new.person@example.com',
        );
    }

    public function test_a_manager_without_the_invite_permission_cannot_invite(): void
    {
        $team = $this->team(User::factory()->create());
        $wrangler = User::factory()->create();
        $team->addMember($wrangler, 'Wrangler');

        // The Invitations page needs the invite permission; managing members alone isn't enough.
        Livewire::actingAs($wrangler)
            ->test(ListInvitations::class, ['team' => $team])
            ->assertForbidden();

        $this->actingAs($wrangler)
            ->get(route('team.members', ['team' => $team->slug]))
            ->assertOk()
            ->assertDontSee(route('team.invitations', ['team' => $team->slug]));
    }

    public function test_invitations_are_unavailable_when_teams_are_admin_provisioned(): void
    {
        config(['teams.creation' => 'admin-provisioned']);
        $owner = User::factory()->create();
        $team = $this->team($owner);

        // No page for members in this mode (and no nav item — see TeamsServiceProvider).
        Livewire::actingAs($owner)
            ->test(ListInvitations::class, ['team' => $team])
            ->assertNotFound();

        $this->expectException(RuntimeException::class);
        app(InviteMember::class)($team, 'someone@example.com', null, $owner);
    }

    public function test_an_existing_member_cannot_be_invited(): void
    {
        $owner = User::factory()->create();
        $team = $this->team($owner);
        $member = User::factory()->create(['email' => 'member@example.com']);
        $team->addMember($member, 'Member');

        Livewire::actingAs($owner)
            ->test(ListInvitations::class, ['team' => $team])
            ->callAction('invite', data: ['email' => 'MEMBER@example.com'])
            ->assertHasActionErrors(['email']);

        $this->assertDatabaseMissing('team_invitations', ['email' => 'member@example.com']);
    }

    public function test_a_pending_invitation_can_be_resent_and_revoked(): void
    {
        Notification::fake();
        $owner = User::factory()->create();
        $team = $this->team($owner);
        $invitation = TeamInvitation::factory()->create(['team_id' => $team->id, 'email' => 'pending@example.com']);

        $component = Livewire::actingAs($owner)
            ->test(PendingInvitations::class, ['team' => $team])
            ->assertSee('pending@example.com')
            ->callTableAction('resend', $invitation);

        Notification::assertSentOnDemand(TeamInvitationNotification::class);

        $component->callTableAction('revoke', $invitation);

        $this->assertModelMissing($invitation);
    }

    public function test_a_manager_without_the_invite_permission_cannot_see_pending_invitations(): void
    {
        $team = $this->team(User::factory()->create());
        $wrangler = User::factory()->create();
        $team->addMember($wrangler, 'Wrangler');

        Livewire::actingAs($wrangler)
            ->test(PendingInvitations::class, ['team' => $team])
            ->assertForbidden();
    }

    public function test_accepting_an_invitation_joins_the_team_with_the_invited_role(): void
    {
        $team = $this->team(User::factory()->create());
        $invitee = User::factory()->create(['email' => 'invitee@example.com']);
        $invitation = TeamInvitation::factory()->create(['team_id' => $team->id, 'email' => 'invitee@example.com', 'role' => 'Team Admin']);

        $this->actingAs($invitee)
            ->get($invitation->acceptUrl())
            ->assertRedirect(route('team.dashboard', ['team' => $team->slug]));

        $this->assertTrue($team->fresh()->hasUser($invitee));
        $this->assertSame('Team Admin', $team->roleFor($invitee));
        $this->assertModelMissing($invitation);
        $this->assertSame($team->id, $invitee->fresh()->current_team_id);
    }

    public function test_an_invitation_cannot_be_accepted_by_a_different_account(): void
    {
        $team = $this->team(User::factory()->create());
        $invitation = TeamInvitation::factory()->create(['team_id' => $team->id, 'email' => 'invitee@example.com']);
        $someoneElse = User::factory()->create();

        $this->actingAs($someoneElse)
            ->get($invitation->acceptUrl())
            ->assertForbidden();

        $this->assertFalse($team->fresh()->hasUser($someoneElse));
        $this->assertModelExists($invitation);
    }

    public function test_the_accept_link_must_be_signed(): void
    {
        $team = $this->team(User::factory()->create());
        $invitee = User::factory()->create(['email' => 'invitee@example.com']);
        $invitation = TeamInvitation::factory()->create(['team_id' => $team->id, 'email' => 'invitee@example.com']);

        $this->actingAs($invitee)
            ->get(route('team.invitations.accept', $invitation))
            ->assertForbidden();

        $this->assertFalse($team->fresh()->hasUser($invitee));
    }

    public function test_a_guest_with_an_account_signs_in_and_comes_back_to_accept(): void
    {
        $team = $this->team(User::factory()->create());
        $invitee = User::factory()->create(['email' => 'invitee@example.com']);
        $invitation = TeamInvitation::factory()->create(['team_id' => $team->id, 'email' => 'invitee@example.com']);

        $this->get($invitation->acceptUrl())
            ->assertRedirect(route('login'))
            ->assertSessionHas('status')
            ->assertSessionHas('url.intended', $invitation->acceptUrl());

        // …and after signing in, the intended URL is the signed accept link.
        $this->actingAs($invitee)
            ->get($invitation->acceptUrl())
            ->assertRedirect(route('team.dashboard', ['team' => $team->slug]));

        $this->assertTrue($team->fresh()->hasUser($invitee));
    }

    public function test_signing_in_returns_to_the_accept_link_and_joins(): void
    {
        $team = $this->team(User::factory()->create());
        $invitee = User::factory()->create(['email' => 'invitee@example.com']);
        $invitation = TeamInvitation::factory()->create(['team_id' => $team->id, 'email' => 'invitee@example.com']);

        $this->get($invitation->acceptUrl())->assertRedirect(route('login'));

        // Fortify sends a fresh login to the intended URL — the signed accept link.
        $this->post('/login', ['email' => 'invitee@example.com', 'password' => 'password'])
            ->assertRedirect($invitation->acceptUrl());

        $this->get($invitation->acceptUrl())
            ->assertRedirect(route('team.dashboard', ['team' => $team->slug]));

        $this->assertTrue($team->fresh()->hasUser($invitee));
    }

    public function test_a_revoked_invitation_link_is_dead(): void
    {
        $team = $this->team(User::factory()->create());
        $invitee = User::factory()->create(['email' => 'invitee@example.com']);
        $invitation = TeamInvitation::factory()->create(['team_id' => $team->id, 'email' => 'invitee@example.com']);
        $link = $invitation->acceptUrl();
        $registerLink = URL::signedRoute('team.invitations.register', ['invitation' => $invitation]);

        $invitation->delete();

        $this->actingAs($invitee)->get($link)->assertNotFound();
        $this->get($registerLink)->assertNotFound();
        $this->assertFalse($team->fresh()->hasUser($invitee));
    }

    public function test_a_role_deleted_after_inviting_is_simply_not_granted(): void
    {
        $team = $this->team(User::factory()->create());
        $invitee = User::factory()->create(['email' => 'invitee@example.com']);
        $temporary = Team::createRole('Temporary');
        $invitation = TeamInvitation::factory()->create(['team_id' => $team->id, 'email' => 'invitee@example.com', 'role' => 'Temporary']);
        $temporary->delete();

        $this->actingAs($invitee)->get($invitation->acceptUrl())->assertRedirect();

        $this->assertTrue($team->fresh()->hasUser($invitee));
        $this->assertNull($team->roleFor($invitee));
    }

    public function test_an_invitation_to_an_inactive_team_cannot_be_accepted(): void
    {
        $team = Team::factory()->inactive()->create();
        $invitee = User::factory()->create(['email' => 'invitee@example.com']);
        $invitation = TeamInvitation::factory()->create(['team_id' => $team->id, 'email' => 'invitee@example.com']);

        $this->actingAs($invitee)->get($invitation->acceptUrl())->assertForbidden();

        $this->assertFalse($team->fresh()->hasUser($invitee));
        $this->assertModelExists($invitation, 'kept for when the team is reactivated');
    }

    public function test_the_email_names_the_team_and_links_to_the_signed_accept_url(): void
    {
        $team = $this->team(User::factory()->create());
        $invitation = TeamInvitation::factory()->create(['team_id' => $team->id, 'email' => 'invitee@example.com']);

        $mail = (new TeamInvitationNotification($invitation))->toMail(new AnonymousNotifiable);

        $this->assertStringContainsString($team->name, $mail->subject);
        $this->assertSame($invitation->acceptUrl(), $mail->actionUrl);
        $this->assertStringContainsString('invitee@example.com', implode(' ', $mail->introLines));
    }

    public function test_a_guest_without_an_account_registers_through_the_invitation(): void
    {
        $team = $this->team(User::factory()->create());
        $invitation = TeamInvitation::factory()->create(['team_id' => $team->id, 'email' => 'newcomer@example.com', 'role' => 'Member']);

        $registerUrl = $this->get($invitation->acceptUrl())->headers->get('Location');
        $this->assertStringContainsString(route('team.invitations.register', $invitation), $registerUrl);

        $this->get($registerUrl)
            ->assertOk()
            ->assertSee($team->name)
            ->assertSee('newcomer@example.com');

        $storeUrl = URL::signedRoute('team.invitations.register.store', ['invitation' => $invitation]);

        $this->post($storeUrl, [
            'first_name' => 'New',
            'last_name' => 'Comer',
            'password' => 'Str0ng-passw0rd!',
            'password_confirmation' => 'Str0ng-passw0rd!',
        ])->assertRedirect(route('team.dashboard', ['team' => $team->slug]));

        $user = User::query()->where('email', 'newcomer@example.com')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertTrue($user->hasVerifiedEmail(), 'the signed link proved the address');
        $this->assertTrue($team->fresh()->hasUser($user));
        $this->assertSame('Member', $team->roleFor($user));
        $this->assertModelMissing($invitation);
    }

    public function test_the_invitation_registration_pages_must_be_signed(): void
    {
        $team = $this->team(User::factory()->create());
        $invitation = TeamInvitation::factory()->create(['team_id' => $team->id]);

        $this->get(route('team.invitations.register', $invitation))->assertForbidden();
        $this->post(route('team.invitations.register.store', $invitation), [])->assertForbidden();
    }

    public function test_registering_through_an_invitation_ignores_a_submitted_email(): void
    {
        $team = $this->team(User::factory()->create());
        $invitation = TeamInvitation::factory()->create(['team_id' => $team->id, 'email' => 'invited@example.com']);
        $storeUrl = URL::signedRoute('team.invitations.register.store', ['invitation' => $invitation]);

        $this->post($storeUrl, [
            'email' => 'someone-else@example.com',
            'first_name' => 'New',
            'last_name' => 'Comer',
            'password' => 'Str0ng-passw0rd!',
            'password_confirmation' => 'Str0ng-passw0rd!',
        ])->assertRedirect();

        $this->assertDatabaseHas('users', ['email' => 'invited@example.com']);
        $this->assertDatabaseMissing('users', ['email' => 'someone-else@example.com']);
    }

    public function test_a_signed_in_user_is_sent_from_the_registration_page_to_accept(): void
    {
        $team = $this->team(User::factory()->create());
        $invitation = TeamInvitation::factory()->create(['team_id' => $team->id, 'email' => 'invitee@example.com']);
        $invitee = User::factory()->create(['email' => 'invitee@example.com']);

        $this->actingAs($invitee)
            ->get(URL::signedRoute('team.invitations.register', ['invitation' => $invitation]))
            ->assertRedirect($invitation->acceptUrl());
    }

    public function test_accepting_verifies_an_unverified_account(): void
    {
        $team = $this->team(User::factory()->create());
        $invitee = User::factory()->unverified()->create(['email' => 'invitee@example.com']);
        $invitation = TeamInvitation::factory()->create(['team_id' => $team->id, 'email' => 'invitee@example.com']);

        $this->actingAs($invitee)->get($invitation->acceptUrl())->assertRedirect();

        $this->assertTrue($invitee->fresh()->hasVerifiedEmail());
    }

    protected function tearDown(): void
    {
        app(TeamContext::class)->clear();

        parent::tearDown();
    }
}
