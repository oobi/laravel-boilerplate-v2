<?php

namespace Tests\Feature\Scenarios;

use App\Models\User;
use Concise\Teams\Livewire\Team\Onboarding;
use Concise\Teams\Livewire\Team\PendingInvitations;
use Concise\Teams\Models\Team;
use Concise\Teams\Support\TeamContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Scenario 2 — Public SaaS, the default shipped shape (docs/config-recipes.md).
 *
 * self-service creation + member & admin invitations on + public login
 * fallback: anyone signs up, creates their own team, and invites their staff.
 *
 * Walks the config-SENSITIVE junctions under this one combined config so a
 * change that quietly breaks the recipe fails here. Fine-grained rules live in
 * TeamCreationTest / TeamInvitationsTest / LoginRedirectTest; this proves they
 * hold together for the recipe.
 *
 * Maintenance: when a config-sensitive feature is added, extend each scenario
 * class in this directory — not just the default suite.
 */
class PublicSaasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'teams.creation' => 'self-service',
            'teams.invitations.members' => true,
            'teams.invitations.admins' => true,
            'fortify.login_fallback' => 'home',
        ]);
    }

    public function test_a_user_can_create_their_own_team(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(Onboarding::class)
            ->assertActionVisible('createTeam')
            ->assertSee('Create one to get started');
    }

    public function test_an_owner_can_invite_members(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->ownedBy($owner)->create();

        Livewire::actingAs($owner)
            ->test(PendingInvitations::class, ['team' => $team])
            ->assertActionVisible('invite');
    }

    public function test_login_routes_a_teamless_user_to_create_one(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('team.index'));

        $this->actingAs($user)->get(route('team.onboarding'))
            ->assertOk()
            ->assertSee('Create one to get started');
    }

    protected function tearDown(): void
    {
        app(TeamContext::class)->clear();

        parent::tearDown();
    }
}
