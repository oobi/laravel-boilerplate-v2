<?php

declare(strict_types=1);

namespace Tests\Feature\Scenarios;

use App\Http\Controllers\LoginOnlyHomeController;
use App\Models\User;
use App\Support\Auth\LoginRedirectRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Scenario: Backoffice without the teams tier (docs/boilerplate/config-recipes.md).
 *
 * `bp:setup --recipe=backoffice --without-teams`: login-only, admins create
 * every account, LOGIN_FALLBACK=reject. Only a user with a system role gets
 * in; anyone else is turned away with a "contact an admin" notice. As in
 * VanillaAppTest, the teams-less install is reproduced by clearing the login
 * resolvers.
 *
 * The public landing page and sign-up are removed by bp:make-login-only, not
 * config, so the cases that need them gone run only once it has been applied
 * (they skip in the boilerplate itself).
 */
class BackofficeAppTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        LoginRedirectRegistry::flush();
        config(['fortify.login_fallback' => 'reject']);
    }

    private function skipUnlessLoginOnly(): void
    {
        if (Route::getRoutes()->getByName('home')?->getControllerClass() !== LoginOnlyHomeController::class) {
            $this->markTestSkipped('bp:make-login-only has not been applied to this app.');
        }
    }

    public function test_a_system_user_lands_on_the_admin_dashboard(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->post('/login', ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard'));
    }

    public function test_a_user_without_a_system_role_is_turned_away_at_login(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors(['email' => __('auth.no_workspace')]);

        $this->assertGuest();
    }

    public function test_there_is_no_public_page_or_sign_up(): void
    {
        $this->skipUnlessLoginOnly();

        $this->get('/')->assertRedirect(route('login'));
        $this->get('/register')->assertNotFound();
        $this->get(route('login'))->assertOk()->assertDontSee(__('Need an account?'));
    }

    public function test_a_signed_in_user_without_a_role_is_signed_out_rather_than_sent_home(): void
    {
        $this->skipUnlessLoginOnly();

        // e.g. a remember-me login from before their role was removed, opening a guest page.
        $this->actingAs(User::factory()->create())
            ->get(route('login'))
            ->assertRedirect(route('home'));

        $this->get(route('home'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => __('auth.no_workspace')]);

        $this->assertGuest();
    }
}
