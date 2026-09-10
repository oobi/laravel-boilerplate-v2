<?php

namespace Tests\Feature\Teams\Admin;

use App\Models\User;
use Concise\Teams\Livewire\Admin\Teams\TeamInvitations;
use Concise\Teams\Livewire\Team\PendingInvitations;
use Concise\Teams\Models\Team;
use Concise\Teams\Models\TeamInvitation;
use Concise\Teams\Notifications\TeamInvitationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
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

    public function test_a_system_admin_can_invite_even_when_teams_are_admin_provisioned(): void
    {
        Notification::fake();
        config(['teams.creation' => 'admin-provisioned']);

        Livewire::actingAs($this->admin)
            ->test(TeamInvitations::class, ['team' => $this->team])
            ->callAction('invite', data: ['email' => 'new@example.com', 'role' => 'Member'])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('team_invitations', ['team_id' => $this->team->id, 'email' => 'new@example.com', 'role' => 'Member']);
        Notification::assertSentOnDemand(TeamInvitationNotification::class);
    }
}
