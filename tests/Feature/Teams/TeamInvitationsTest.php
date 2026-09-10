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

    public function test_guests_log_in_first(): void
    {
        $team = $this->team(User::factory()->create());
        $invitation = TeamInvitation::factory()->create(['team_id' => $team->id]);

        $this->get($invitation->acceptUrl())->assertRedirect(route('login'));
    }

    protected function tearDown(): void
    {
        app(TeamContext::class)->clear();

        parent::tearDown();
    }
}
