<?php

namespace Tests\Feature\Teams\Admin;

use App\Models\User;
use Concise\Teams\Actions\InviteMember;
use Concise\Teams\Livewire\Team\PendingInvitations;
use Concise\Teams\Models\Team;
use Concise\Teams\Models\TeamInvitation;
use Concise\Teams\Notifications\TeamInvitationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class TeamInvitationsPageTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        Team::createRole('Member');

        $this->admin = User::factory()->superAdmin()->create();
        $this->team = Team::factory()->create();
    }

    public function test_pending_invitations_are_listed_searchable_and_revocable(): void
    {
        $pending = TeamInvitation::factory()->create(['team_id' => $this->team->id, 'email' => 'pending@example.com']);
        $other = TeamInvitation::factory()->create(['team_id' => $this->team->id, 'email' => 'other@example.com']);

        $this->actingAs($this->admin)
            ->get(route('teams.invitations', $this->team))
            ->assertOk()
            ->assertSee('pending@example.com');

        Livewire::actingAs($this->admin)
            ->test(PendingInvitations::class, ['team' => $this->team])
            ->searchTable('pending')
            ->assertCanSeeTableRecords([$pending])
            ->assertCanNotSeeTableRecords([$other])
            ->callTableAction('revoke', $pending);

        $this->assertModelMissing($pending);
    }

    public function test_a_system_admin_can_still_invite_when_only_member_invitations_are_off(): void
    {
        Notification::fake();
        config(['teams.invitations.members' => false]);

        Livewire::actingAs($this->admin)
            ->test(PendingInvitations::class, ['team' => $this->team])
            ->callAction('invite', data: ['email' => 'new@example.com', 'role' => 'Member'])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('team_invitations', ['team_id' => $this->team->id, 'email' => 'new@example.com', 'role' => 'Member']);
        Notification::assertSentOnDemand(TeamInvitationNotification::class);
    }

    public function test_admin_invitations_can_be_switched_off(): void
    {
        config(['teams.invitations.admins' => false]);

        // The admin's Invitations page 404s — an admin adds members directly instead.
        $this->actingAs($this->admin)
            ->get(route('teams.invitations', $this->team))
            ->assertNotFound();

        // …and the write boundary refuses a system-admin inviter, whatever the UI does.
        $this->expectException(RuntimeException::class);
        app(InviteMember::class)($this->team, 'new@example.com', 'Member', $this->admin);
    }

    public function test_a_full_backoffice_leaves_no_invite_surface_at_all(): void
    {
        config(['teams.invitations.members' => false, 'teams.invitations.admins' => false]);

        // Both switches off: the shared invitations component refuses to mount for anyone —
        // even a super admin who bypasses every ability. No invite surface exists.
        Livewire::actingAs($this->admin)
            ->test(PendingInvitations::class, ['team' => $this->team])
            ->assertForbidden();
    }
}
