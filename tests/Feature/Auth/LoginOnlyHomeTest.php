<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Actions\Impersonation\StartImpersonation;
use App\Http\Controllers\LoginOnlyHomeController;
use App\Models\User;
use App\Support\Auth\LoginRedirectRegistry;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The `/` of a login-only app (bp:make-login-only). Mounted on its own URL here,
 * so it's covered whether or not this app still has its public landing page.
 */
class LoginOnlyHomeTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/login-only-home';

    protected function setUp(): void
    {
        parent::setUp();

        LoginRedirectRegistry::flush();
        Route::middleware('web')->get(self::URL, LoginOnlyHomeController::class);
    }

    public function test_a_guest_is_sent_to_login(): void
    {
        $this->get(self::URL)->assertRedirect(route('login'));
    }

    public function test_an_admin_is_sent_to_the_dashboard(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->get(self::URL)
            ->assertRedirect(route('dashboard'));
    }

    public function test_a_user_an_add_on_claims_is_sent_there(): void
    {
        LoginRedirectRegistry::register(fn (User $user): string => 'https://example.test/somewhere');

        $this->actingAs(User::factory()->create())
            ->get(self::URL)
            ->assertRedirect('https://example.test/somewhere');
    }

    public function test_a_user_nothing_claims_is_signed_out_with_a_notice(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(self::URL)
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => __('auth.no_workspace')]);

        $this->assertGuest();
    }

    public function test_an_impersonated_user_nothing_claims_is_handed_back_to_the_impersonator(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $target = User::factory()->create();
        $this->actingAs($admin);
        app(StartImpersonation::class)->handle($target, route('users.index'));

        $this->get(self::URL)->assertRedirect(route('users.index'));

        $this->assertAuthenticatedAs($admin);
        Notification::assertNotified(__('admin.impersonation_ended_no_access', ['name' => $target->name]));
    }
}
