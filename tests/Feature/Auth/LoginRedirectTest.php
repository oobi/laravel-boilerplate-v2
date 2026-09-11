<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Support\Auth\LoginRedirectRegistry;
use Concise\Teams\Models\Team;
use Concise\Teams\Support\TeamContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Post-login routing (App\Http\Responses\LoginResponse): a fixed cascade —
 * system role → admin dashboard, else the first add-on resolver (the teams tier
 * sends non-admins to the team area) — with only the "neither" tail configurable
 * via `fortify.login_fallback`.
 */
class LoginRedirectTest extends TestCase
{
    use RefreshDatabase;

    private function login(User $user): TestResponse
    {
        return $this->post('/login', ['email' => $user->email, 'password' => 'password']);
    }

    public function test_a_system_user_lands_on_the_admin_dashboard(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->login($admin)->assertRedirect(route('dashboard'));
    }

    public function test_a_non_admin_with_a_team_lands_in_the_team_area(): void
    {
        $user = User::factory()->create();
        Team::factory()->ownedBy($user)->create();

        $this->login($user)->assertRedirect(route('team.index'));
    }

    public function test_a_non_admin_with_no_team_still_lands_in_the_team_area(): void
    {
        // TeamRedirect then shows the "ask an admin" onboarding — the tier owns that decision.
        $user = User::factory()->create();

        $this->login($user)->assertRedirect(route('team.index'));
    }

    public function test_a_user_no_resolver_claims_falls_back_to_the_public_home(): void
    {
        LoginRedirectRegistry::flush(); // simulate the teams tier not being installed
        config(['fortify.login_fallback' => 'home']);
        $user = User::factory()->create();

        $this->login($user)->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_the_reject_fallback_logs_an_unplaceable_user_back_out(): void
    {
        LoginRedirectRegistry::flush();
        config(['fortify.login_fallback' => 'reject']);
        $user = User::factory()->create();

        $this->login($user)
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_a_deep_linked_destination_wins_over_the_default_cascade(): void
    {
        $admin = User::factory()->superAdmin()->create();

        // Hitting a guarded page while logged out stashes it as the intended URL…
        $this->get(route('team.index'))->assertRedirect(route('login'));

        // …so after login the admin lands there, not on the dashboard the cascade would default to.
        $this->login($admin)->assertRedirect(route('team.index'));
    }

    public function test_a_system_user_reaches_the_dashboard_regardless_of_resolvers(): void
    {
        LoginRedirectRegistry::flush();
        config(['fortify.login_fallback' => 'reject']);
        $admin = User::factory()->superAdmin()->create();

        // The admin branch is resolved before any resolver or the fallback.
        $this->login($admin)->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($admin);
    }

    protected function tearDown(): void
    {
        app(TeamContext::class)->clear();

        parent::tearDown();
    }
}
