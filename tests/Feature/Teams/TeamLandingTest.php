<?php

namespace Tests\Feature\Teams;

use App\Actions\Impersonation\StartImpersonation;
use App\Livewire\Admin\Users\ShowUser;
use App\Models\User;
use Concise\Teams\Models\Team;
use Concise\Teams\Support\TeamContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Where the teams tier's login resolver (TeamDestination) sends a non-admin:
 * straight into their team, or to onboarding with none. Core's cascade is in
 * Tests\Feature\Auth\LoginRedirectTest.
 */
class TeamLandingTest extends TestCase
{
    use RefreshDatabase;

    private function login(User $user): TestResponse
    {
        return $this->post('/login', ['email' => $user->email, 'password' => 'password']);
    }

    public function test_a_non_admin_with_a_team_lands_directly_on_it(): void
    {
        // One hop, not a bounce through the /teams front door.
        $user = User::factory()->create();
        $team = Team::factory()->ownedBy($user)->create();

        $this->login($user)->assertRedirect(team_route('team.dashboard', $team));
    }

    public function test_a_non_admin_with_no_team_lands_on_onboarding(): void
    {
        // The tier owns the "ask an admin" onboarding decision.
        $user = User::factory()->create();

        $this->login($user)->assertRedirect(route('team.onboarding'));
    }

    public function test_impersonation_lands_on_the_targets_own_team(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $target = User::factory()->create();
        $team = Team::factory()->create();
        $team->addMember($target);

        // You see what they'd see after login: straight into their team.
        Livewire::actingAs($admin)
            ->test(ShowUser::class, ['user' => $target])
            ->callAction('impersonate')
            ->assertRedirect(route('team.dashboard', ['team' => $team->slug]));

        $this->assertAuthenticatedAs($target);
    }

    public function test_a_missing_team_still_offers_a_way_out_of_impersonation(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());
        app(StartImpersonation::class)->handle(User::factory()->create());

        $this->get(route('team.dashboard', ['team' => 'no-such-team']))
            ->assertNotFound()
            ->assertSeeHtml('action="'.route('users.impersonate.leave').'"');
    }

    protected function tearDown(): void
    {
        app(TeamContext::class)->clear();

        parent::tearDown();
    }
}
