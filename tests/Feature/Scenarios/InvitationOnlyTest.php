<?php

namespace Tests\Feature\Scenarios;

use App\Models\User;
use Concise\Teams\Actions\InviteMember;
use Concise\Teams\Livewire\Team\Onboarding;
use Concise\Teams\Livewire\Team\PendingInvitations;
use Concise\Teams\Models\Team;
use Concise\Teams\Support\TeamContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Scenario 3 — Invitation-only (docs/config-recipes.md).
 *
 * admin-only creation + member & admin invitations on + public login fallback:
 * admins seed the teams, and existing members grow them by invitation. Users
 * can't spin up their own team.
 *
 * Walks the config-SENSITIVE junctions under this combined config. See
 * PublicSaasTest for the shared intent and maintenance note.
 */
class InvitationOnlyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'teams.creation' => 'admin-only',
            'teams.invitations.members' => true,
            'teams.invitations.admins' => true,
            'fortify.login_fallback' => 'home',
        ]);
    }

    public function test_self_service_creation_is_closed_but_invites_still_work(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(Onboarding::class)
            ->assertActionHidden('createTeam')
            ->assertSee('Ask an administrator');

        // Members invitations stay on, so an owner can still bring people in.
        $owner = User::factory()->create();
        $team = Team::factory()->ownedBy($owner)->create();

        Livewire::actingAs($owner)
            ->test(PendingInvitations::class, ['team' => $team])
            ->assertActionVisible('invite');
    }

    public function test_an_invited_person_joins_through_the_link(): void
    {
        Notification::fake();
        Team::createRole('Member');
        $owner = User::factory()->create();
        $team = Team::factory()->ownedBy($owner)->create();
        $invitee = User::factory()->create(['email' => 'newbie@example.com']);

        // The recipe's defining flow: an owner invites by email, the invitee
        // follows the signed link and joins with the invited role.
        $invitation = app(InviteMember::class)($team, 'newbie@example.com', 'Member', $owner);

        $this->actingAs($invitee)
            ->get($invitation->acceptUrl())
            ->assertRedirect(route('team.dashboard', ['team' => $team->slug]));

        $this->assertTrue($team->fresh()->hasUser($invitee));
    }

    public function test_login_sends_a_teamless_user_to_ask_an_admin(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('team.index'));

        $this->actingAs($user)->get(route('team.onboarding'))
            ->assertOk()
            ->assertSee('Ask an administrator')
            ->assertDontSee('Create one to get started');
    }

    protected function tearDown(): void
    {
        app(TeamContext::class)->clear();

        parent::tearDown();
    }
}
