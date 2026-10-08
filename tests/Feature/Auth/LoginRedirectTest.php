<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Support\Auth\Destination;
use App\Support\Auth\LoginRedirectRegistry;
use Concise\Teams\Models\Team;
use Concise\Teams\Support\TeamContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Mockery\MockInterface;
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

    public function test_a_non_admin_with_a_team_lands_directly_on_it(): void
    {
        // One hop, not a bounce through the /teams front door — see TeamDestination.
        $user = User::factory()->create();
        $team = Team::factory()->ownedBy($user)->create();

        $this->login($user)->assertRedirect(team_route('team.dashboard', $team));
    }

    public function test_a_non_admin_with_no_team_lands_on_onboarding(): void
    {
        // The tier owns the "ask an admin" onboarding decision — see TeamDestination.
        $user = User::factory()->create();

        $this->login($user)->assertRedirect(route('team.onboarding'));
    }

    public function test_a_user_no_resolver_claims_falls_back_to_the_public_home(): void
    {
        LoginRedirectRegistry::flush(); // simulate the teams tier not being installed
        config(['fortify.login_fallback' => 'home']);
        $user = User::factory()->create();

        $this->login($user)->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_the_reject_fallback_refuses_an_unplaceable_user_at_the_password(): void
    {
        LoginRedirectRegistry::flush();
        config(['fortify.login_fallback' => 'reject']);
        $user = User::factory()->create();

        $this->from(route('login'))->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => __('auth.no_workspace')]);

        $this->assertGuest();
        // Refused before logging in, so it isn't recorded as one (GitHub #20).
        $this->assertNull($user->fresh()->last_login_at);
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

    /** A login through the two-factor challenge lands where a password login does (GitHub #20). */
    public function test_a_two_factor_login_lands_on_the_dashboard_for_an_admin(): void
    {
        $this->mock(TwoFactorAuthenticationProvider::class, fn (MockInterface $mock) => $mock->shouldReceive('verify')->andReturnTrue());
        $admin = User::factory()->superAdmin()->twoFactorEnabled()->create();

        $this->login($admin)->assertRedirect('/two-factor-challenge');

        $this->post('/two-factor-challenge', ['code' => '123456'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($admin);
    }

    /** With nowhere to land, a two-factor user is refused at the password, never sent on to the challenge. */
    public function test_the_reject_fallback_refuses_a_two_factor_user_before_the_challenge(): void
    {
        LoginRedirectRegistry::flush();
        config(['fortify.login_fallback' => 'reject']);
        $user = User::factory()->twoFactorEnabled()->create();

        $this->from(route('login'))->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => __('auth.no_workspace')])
            ->assertSessionMissing('login.id');

        $this->assertGuest();
    }

    /** A signed-in user opening a guest page goes where they belong, not to the admin dashboard (GitHub #21). */
    public function test_a_signed_in_user_opening_login_goes_where_they_belong(): void
    {
        $member = User::factory()->create();
        $this->actingAs($member)->get(route('login'))->assertRedirect(Destination::home($member));
        $this->assertNotSame(route('dashboard'), Destination::home($member));

        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin)->get(route('login'))->assertRedirect(route('dashboard'));
    }

    /** The response's own reject: a user who loses their destination between the password and the challenge. */
    public function test_the_reject_backstop_catches_a_user_placed_nowhere_after_the_challenge(): void
    {
        $this->mock(TwoFactorAuthenticationProvider::class, fn (MockInterface $mock) => $mock->shouldReceive('verify')->andReturnTrue());
        config(['fortify.login_fallback' => 'reject']);
        $user = User::factory()->twoFactorEnabled()->create();
        $this->login($user)->assertRedirect('/two-factor-challenge');

        LoginRedirectRegistry::flush();

        $this->post('/two-factor-challenge', ['code' => '123456'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => __('auth.no_workspace')]);
        $this->assertGuest();
    }

    public function test_a_signed_in_user_nothing_claims_goes_to_the_public_home(): void
    {
        LoginRedirectRegistry::flush();

        $this->actingAs(User::factory()->create())->get(route('login'))->assertRedirect(route('home'));
    }

    protected function tearDown(): void
    {
        app(TeamContext::class)->clear();

        parent::tearDown();
    }
}
