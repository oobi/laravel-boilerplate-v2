<?php

namespace Tests\Feature\Scenarios;

use App\Models\User;
use Concise\Teams\Livewire\Team\ListInvitations;
use Concise\Teams\Livewire\Team\MembersTable;
use Concise\Teams\Livewire\Team\Onboarding;
use Concise\Teams\Livewire\Team\PendingInvitations;
use Concise\Teams\Models\Team;
use Concise\Teams\Support\TeamContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Scenario 4 — Backoffice / administration platform (docs/config-recipes.md).
 *
 * admin-only creation + both invitation switches OFF + reject fallback: a
 * purely internal tool. Admins create the accounts and the teams and add staff
 * directly; there is no invitation surface anywhere.
 *
 * Walks the config-SENSITIVE junctions under this combined config. See
 * PublicSaasTest for the shared intent and maintenance note. (Removing the
 * public landing page and closing registration are scaffold steps for the
 * bp:setup work, not runtime config, so they're out of scope here.)
 */
class BackofficeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'teams.creation' => 'admin-only',
            'teams.invitations.members' => false,
            'teams.invitations.admins' => false,
            'fortify.login_fallback' => 'reject',
        ]);
    }

    public function test_there_is_no_invitation_surface(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->ownedBy($owner)->create();

        // The team-area invitations page is gone…
        Livewire::actingAs($owner)
            ->test(ListInvitations::class, ['team' => $team])
            ->assertNotFound();

        // …the admin-area one too…
        $this->actingAs(User::factory()->superAdmin()->create())
            ->get(route('teams.invitations', $team))
            ->assertNotFound();

        // …and the shared invitations component refuses to mount for anyone.
        Livewire::actingAs($owner)
            ->test(PendingInvitations::class, ['team' => $team])
            ->assertForbidden();
    }

    public function test_admins_add_members_directly_instead(): void
    {
        $team = Team::factory()->create();

        Livewire::actingAs(User::factory()->superAdmin()->create())
            ->test(MembersTable::class, ['team' => $team])
            ->assertActionVisible('addMember');
    }

    public function test_self_service_creation_is_closed(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(Onboarding::class)
            ->assertActionHidden('createTeam')
            ->assertSee('Ask an administrator');
    }

    public function test_login_routes_by_role_and_membership(): void
    {
        // A system user reaches the admin dashboard.
        $admin = User::factory()->superAdmin()->create();
        $this->post('/login', ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard'));
        $this->post('/logout');

        // A non-admin with no team lands on the "ask an admin" onboarding. (With the
        // teams tier installed its resolver claims every non-admin, so the reject
        // fallback is the safety net for a teams-less build, not this path.)
        $user = User::factory()->create();
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('team.index'));

        $this->actingAs($user)->get(route('team.onboarding'))
            ->assertOk()
            ->assertSee('Ask an administrator');
    }

    protected function tearDown(): void
    {
        app(TeamContext::class)->clear();

        parent::tearDown();
    }
}
