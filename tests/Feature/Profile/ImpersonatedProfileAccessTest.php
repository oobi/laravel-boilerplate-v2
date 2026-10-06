<?php

namespace Tests\Feature\Profile;

use App\Actions\Impersonation\StartImpersonation;
use App\Enums\SystemPermission;
use App\Http\Middleware\DenyLowAssuranceSessions;
use App\Livewire\Profile\EditPassword;
use App\Livewire\Profile\EditProfile;
use App\Livewire\Profile\TwoFactorAuthentication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

/**
 * An impersonator never proved they are the account holder, so they can't
 * change its email, password or two-factor and take it over (GitHub #16).
 */
class ImpersonatedProfileAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $target;

    protected function setUp(): void
    {
        parent::setUp();

        $this->target = User::factory()->create(['email' => 'target@example.com']);
    }

    #[TestWith(['profile.edit'])]
    #[TestWith(['profile.password'])]
    #[TestWith(['profile.two-factor'])]
    public function test_an_impersonator_cant_open_the_profile_pages(string $route): void
    {
        $this->impersonate();

        $this->get(route($route))->assertForbidden();
    }

    /**
     * A save on each profile page, filled in by the target before the
     * impersonation began and submitted by the impersonator.
     *
     * @return array<string, array{class-string, string, array<string, string>}>
     */
    public static function profileActions(): array
    {
        return [
            'change email' => [EditProfile::class, 'updateProfileInformation', ['email' => 'thief@example.com', 'current_password' => 'password']],
            'change password' => [EditPassword::class, 'updatePassword', ['current_password' => 'password', 'password' => 'a-new-long-password-1', 'password_confirmation' => 'a-new-long-password-1']],
            'enable two-factor' => [TwoFactorAuthentication::class, 'enableTwoFactorAuthentication', []],
        ];
    }

    /** @param array<string, string> $input */
    #[DataProvider('profileActions')]
    public function test_an_impersonator_cant_use_an_open_profile_page(string $component, string $action, array $input): void
    {
        $page = Livewire::actingAs($this->target)->test($component);

        foreach ($input as $property => $value) {
            $page->set($property, $value);
        }

        $this->impersonate();

        $page->call($action)->assertForbidden();

        $target = $this->target->fresh();
        $this->assertSame('target@example.com', $target->email);
        $this->assertNull($target->two_factor_secret);
    }

    /** The password prompt the target opened must not switch two-factor on for the impersonator. */
    public function test_an_impersonator_cant_finish_a_two_factor_prompt_the_target_opened(): void
    {
        $page = Livewire::actingAs($this->target)->test(TwoFactorAuthentication::class)
            ->call('enableTwoFactorAuthentication')
            ->set('confirmablePassword', 'password');
        $this->impersonate();

        $page->call('confirmPassword')->assertForbidden();

        $this->assertNull($this->target->fresh()->two_factor_secret);
    }

    /** Passkeys and Fortify's profile and password routes are off; guarded should they be switched on. */
    #[TestWith(['passkey.registration-options', true])]
    #[TestWith(['passkey.store', true])]
    #[TestWith(['passkey.destroy', true])]
    #[TestWith(['user-profile-information.update', true])]
    #[TestWith(['user-password.update', true])]
    #[TestWith(['passkey.login', false])]
    #[TestWith(['two-factor.login', false])]
    public function test_the_guard_covers_routes_that_are_off_but_not_login(string $route, bool $guarded): void
    {
        $this->assertSame($guarded, DenyLowAssuranceSessions::guardsFortifyRoute($route));
    }

    /**
     * Every Fortify two-factor endpoint but the login challenge. The admin's own
     * password confirmation stays in the session while impersonating.
     *
     * @return array<string, array{string, string}>
     */
    public static function fortifyTwoFactorRoutes(): array
    {
        return [
            'enable' => ['post', 'two-factor.enable'],
            'confirm' => ['post', 'two-factor.confirm'],
            'disable' => ['delete', 'two-factor.disable'],
            'qr code' => ['get', 'two-factor.qr-code'],
            'secret key' => ['get', 'two-factor.secret-key'],
            'recovery codes' => ['get', 'two-factor.recovery-codes'],
            'regenerate recovery codes' => ['post', 'two-factor.regenerate-recovery-codes'],
        ];
    }

    #[DataProvider('fortifyTwoFactorRoutes')]
    public function test_an_impersonator_cant_use_fortifys_two_factor_routes(string $method, string $route): void
    {
        $this->impersonate();

        $this->withSession(['auth.password_confirmed_at' => time()])
            ->{$method}(route($route))
            ->assertForbidden();
    }

    /** They would be a second, unguarded way to change the email or password. */
    public function test_fortifys_profile_and_password_routes_are_off(): void
    {
        $this->assertFalse(Route::has('user-profile-information.update'));
        $this->assertFalse(Route::has('user-password.update'));
    }

    public function test_the_account_menu_hides_profile_while_impersonating(): void
    {
        $this->target = User::factory()->withPermission(SystemPermission::ACCESS_ADMIN_PANEL)->create();
        $this->impersonate();

        $this->get(route('dashboard'))->assertOk()->assertDontSee(route('profile.edit'));
    }

    private function impersonate(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());
        app(StartImpersonation::class)->handle($this->target);
    }
}
