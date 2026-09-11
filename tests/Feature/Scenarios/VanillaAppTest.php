<?php

namespace Tests\Feature\Scenarios;

use App\Models\User;
use App\Support\Auth\LoginRedirectRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Scenario 1 — Vanilla app, no teams tier (docs/config-recipes.md).
 *
 * The base boilerplate with the teams package NOT installed: public
 * registration, and login routing that knows only "system role → dashboard,
 * everyone else → the public home". A teams-less install has no login resolver
 * registered; we reproduce that here by clearing the registry, since the teams
 * tier is a path package that is always loaded in this repo.
 *
 * Intent: the core auth flow must stand on its own. A change to the login seam
 * that assumes teams (or any add-on) is present should fail here.
 *
 * Maintenance: when a config-sensitive feature touches core auth, assert it in
 * this class as well as its siblings in this directory.
 */
class VanillaAppTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        LoginRedirectRegistry::flush();               // no add-on contributes a destination
        config(['fortify.login_fallback' => 'home']); // a public-facing app
    }

    public function test_public_registration_is_available(): void
    {
        $this->get('/register')->assertOk();
    }

    public function test_a_non_admin_lands_on_the_public_home(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_a_system_user_lands_on_the_admin_dashboard(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->post('/login', ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard'));
    }
}
