<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\SystemPermission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Livewire\Drawer\Utils;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class InactiveUserAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_deactivated_user_is_logged_out_on_next_request(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);
        $user->update(['active' => false]);

        $this->get('/')
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_deactivated_user_is_logged_out_from_admin_route(): void
    {
        $user = User::factory()
            ->withPermission(SystemPermission::ACCESS_ADMIN_PANEL)
            ->create();

        $this->actingAs($user);
        $user->update(['active' => false]);

        $this->get('/admin/dashboard')
            ->assertRedirect('/login');

        $this->assertGuest();
    }

    public function test_active_user_is_not_affected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/')
            ->assertOk();
    }

    /** @param array<string, string> $credentials */
    #[DataProvider('twoFactorCredentials')]
    public function test_deactivated_user_cannot_complete_two_factor_challenge(array $credentials): void
    {
        $user = User::factory()->twoFactorEnabled()->create();
        $this->beginTwoFactorChallenge($user);
        $user->update(['active' => false]);

        $this->post('/two-factor-challenge', $credentials)
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email')
            ->assertSessionMissing('login.id')
            ->assertSessionMissing('login.remember');

        $this->assertGuest();
        $this->assertSame(['test-recovery-code'], $user->fresh()->recoveryCodes());
    }

    public function test_two_factor_challenge_view_blocked_for_deactivated_user(): void
    {
        $user = User::factory()->twoFactorEnabled()->create();

        $this->session([
            'login.id' => $user->id,
            'login.remember' => false,
        ]);

        $user->update(['active' => false]);

        $this->get('/two-factor-challenge')
            ->assertRedirect('/login')
            ->assertSessionMissing('login.id')
            ->assertSessionMissing('login.remember');
    }

    public function test_active_two_factor_challenge_proceeds_normally(): void
    {
        $user = User::factory()->twoFactorEnabled()->create();

        $this->session([
            'login.id' => $user->id,
            'login.remember' => false,
        ]);

        // The challenge page should render, not redirect.
        $this->get('/two-factor-challenge')
            ->assertOk();
    }

    public function test_middleware_is_skipped_when_block_inactive_users_is_disabled(): void
    {
        config()->set('auth.block_inactive_users', false);

        $user = User::factory()->create();

        $this->actingAs($user);
        $user->update(['active' => false]);

        $this->get('/')
            ->assertOk();
    }

    #[DataProvider('sessionDrivers')]
    public function test_impersonation_session_is_ended_when_target_is_deactivated(string $driver): void
    {
        $admin = User::factory()->superAdmin()->create();
        $target = User::factory()->create();
        $sessionId = $this->startImpersonation($driver, $admin, $target);
        $target->update(['active' => false]);

        $this->resumeSession($sessionId)->get('/admin/profile')
            ->assertRedirect('/login')
            ->assertSessionMissing('impersonated_by')
            ->assertSessionMissing('impersonator_guard')
            ->assertSessionMissing('impersonator_guard_using')
            ->assertSessionMissing('remember_web');

        $this->assertGuest();
        $this->get('/login')->assertOk();
    }

    #[DataProvider('sessionDrivers')]
    public function test_deactivated_impersonator_cannot_continue_editing_the_target(string $driver): void
    {
        $admin = User::factory()->superAdmin()->create();
        $target = User::factory()->create(['first_name' => 'OriginalName']);
        $sessionId = $this->startImpersonation($driver, $admin, $target);
        $admin->update(['active' => false]);

        $this->resumeSession($sessionId)->put('/user/profile-information', [
            'first_name' => 'BlockedChange',
            'last_name' => $target->last_name,
            'email' => $target->email,
        ])->assertRedirect('/login')
            ->assertSessionMissing('impersonated_by');

        $this->assertGuest();
        $this->assertSame('OriginalName', $target->fresh()->first_name);
    }

    #[DataProvider('sessionDrivers')]
    public function test_deleted_impersonator_ends_session_without_a_redirect_loop(string $driver): void
    {
        $admin = User::factory()->superAdmin()->create();
        $target = User::factory()->create();
        $sessionId = $this->startImpersonation($driver, $admin, $target);
        $admin->delete();

        $this->resumeSession($sessionId)->get(route('users.impersonate.leave'))
            ->assertRedirect('/login')
            ->assertSessionMissing('impersonated_by');

        $this->assertGuest();
        $this->get('/login')->assertOk();
    }

    #[DataProvider('sessionDrivers')]
    public function test_deleted_target_discards_the_original_identity(string $driver): void
    {
        $admin = User::factory()->superAdmin()->create();
        $target = User::factory()->create();
        $sessionId = $this->startImpersonation($driver, $admin, $target);
        $target->delete();

        $this->resumeSession($sessionId)->get('/')
            ->assertRedirect('/login')
            ->assertSessionMissing('impersonated_by');

        $this->assertGuest();
    }

    public function test_forced_logout_discards_the_impersonators_remember_cookie(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $target = User::factory()->create();
        $login = $this->post('/login', [
            'email' => $admin->email,
            'password' => 'password',
            'remember' => true,
        ])->assertRedirect();
        $cookieName = Auth::guard('web')->getRecallerName();
        $this->withCookie($cookieName, $login->getCookie($cookieName)->getValue())
            ->get(route('users.impersonate', $target))->assertRedirect();
        $this->assertTrue(session()->has('remember_web'));
        $sessionId = session()->getId();
        $target->update(['active' => false]);

        $this->resumeSession($sessionId)->get('/')->assertRedirect('/login')
            ->assertCookieExpired($cookieName)
            ->assertSessionMissing('remember_web')
            ->assertSessionMissing('impersonated_by');

        $this->assertGuest();
    }

    public function test_livewire_http_update_is_blocked_after_deactivation(): void
    {
        $user = User::factory()->withPermission(SystemPermission::ACCESS_ADMIN_PANEL)->create(['first_name' => 'OriginalName']);
        $page = $this->actingAs($user)->get('/admin/profile')->assertOk();
        $snapshot = Utils::extractAttributeDataFromHtml($page->getContent(), 'wire:snapshot');
        $payload = ['components' => [[
            'snapshot' => json_encode($snapshot),
            'updates' => ['first_name' => 'AllowedChange'],
            'calls' => [['method' => 'updateProfileInformation', 'params' => []]],
        ]]];
        $uri = app('livewire')->getUpdateUri();
        $allowed = $this->withHeader('X-Livewire', 'true')->postJson($uri, $payload)->assertOk();
        $this->assertSame('AllowedChange', $user->fresh()->first_name);
        $payload['components'][0]['snapshot'] = $allowed->json('components.0.snapshot');
        $payload['components'][0]['updates']['first_name'] = 'BlockedChange';
        $user->update(['active' => false]);

        $this->withHeader('X-Livewire', 'true')->postJson($uri, $payload)->assertRedirect('/login');

        $this->assertGuest();
        $this->assertSame('AllowedChange', $user->fresh()->first_name);
    }

    public function test_inactive_pending_challenge_does_not_interrupt_public_pages(): void
    {
        $user = User::factory()->inactive()->twoFactorEnabled()->create();

        $this->withSession(['login.id' => $user->id])->get('/')->assertOk();

        $this->assertGuest();
    }

    public function test_deleted_user_cannot_resume_a_two_factor_challenge(): void
    {
        $user = User::factory()->twoFactorEnabled()->create();
        $this->beginTwoFactorChallenge($user);
        $user->delete();

        $this->get('/two-factor-challenge')
            ->assertRedirect('/login')
            ->assertSessionMissing('login.id')
            ->assertSessionMissing('login.remember');

        $this->assertGuest();
    }

    /** @param array<string, string> $credentials */
    #[DataProvider('twoFactorCredentials')]
    public function test_active_user_can_complete_two_factor_challenge(array $credentials): void
    {
        $user = User::factory()->twoFactorEnabled()->create();
        $this->beginTwoFactorChallenge($user);

        $this->post('/two-factor-challenge', $credentials)
            ->assertRedirect('/')
            ->assertSessionMissing('login.id');

        $this->assertAuthenticatedAs($user);
    }

    public function test_two_factor_challenge_can_complete_when_suspension_blocking_is_disabled(): void
    {
        $user = User::factory()->twoFactorEnabled()->create();
        $this->beginTwoFactorChallenge($user);
        $user->update(['active' => false]);
        config()->set('auth.block_inactive_users', false);

        $this->post('/two-factor-challenge', ['recovery_code' => 'test-recovery-code'])
            ->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
    }

    private function beginTwoFactorChallenge(User $user): void
    {
        $this->mock(TwoFactorAuthenticationProvider::class, function (MockInterface $mock): void {
            $mock->shouldReceive('verify')->with('test-secret', '123456')->andReturnTrue();
        });

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect('/two-factor-challenge');
    }

    private function startImpersonation(string $driver, User $admin, User $target): string
    {
        config()->set('session.driver', $driver);
        $this->post('/login', ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect();
        $this->get(route('users.impersonate', $target))->assertRedirect();
        $this->assertAuthenticatedAs($target);

        return session()->getId();
    }

    /** Reload the browser's stored session instead of reusing the test's cached guard and attributes. */
    private function resumeSession(string $sessionId): static
    {
        app('auth')->forgetGuards();
        session()->flush();

        return $this->withCookie(config('session.cookie'), $sessionId);
    }

    /** @return array<string, array{string}> */
    public static function sessionDrivers(): array
    {
        return [
            'array' => ['array'],
            'database' => ['database'],
        ];
    }

    /** @return array<string, array{array<string, string>}> */
    public static function twoFactorCredentials(): array
    {
        return [
            'TOTP' => [['code' => '123456']],
            'recovery code' => [['recovery_code' => 'test-recovery-code']],
        ];
    }
}
