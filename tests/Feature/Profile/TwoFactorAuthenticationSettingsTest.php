<?php

declare(strict_types=1);

namespace Tests\Feature\Profile;

use App\Livewire\Profile\TwoFactorAuthentication;
use App\Models\User;
use App\Support\Auth\PasswordChecks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Fortify;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/** The Two-Factor tab of the profile area: route access, status, and the guarded setup/teardown flow. */
class TwoFactorAuthenticationSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/profile/two-factor')->assertRedirect('/login');
    }

    /** The profile is not an admin page: any signed-in, verified user reaches it. */
    public function test_users_without_admin_access_can_view_the_two_factor_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/profile/two-factor')->assertOk();
    }

    public function test_admin_users_can_view_the_two_factor_page_and_see_the_disabled_status(): void
    {
        $admin = User::factory()->superAdmin()->create();

        // "Two Factor Authentication" (admin.two_factor_authentication) only ever
        // rendered via the breadcrumb leaf, which the neutral account layout
        // deliberately doesn't show (see layouts.account); the tab label
        // (admin.two_factor) is what's actually on the page.
        $this->actingAs($admin)
            ->get('/profile/two-factor')
            ->assertOk()
            ->assertSee(__('admin.two_factor'))
            ->assertSee(__('admin.disabled'));
    }

    public function test_an_enabled_users_status_reads_enabled(): void
    {
        $admin = User::factory()->superAdmin()->twoFactorEnabled()->create();

        $this->actingAs($admin)->get('/profile/two-factor')->assertSee(__('admin.enabled'));
    }

    public function test_enabling_two_factor_requires_a_password_and_does_nothing_until_confirmed(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(TwoFactorAuthentication::class)
            ->call('enableTwoFactorAuthentication')
            ->assertSet('confirmingPassword', true)
            ->assertSet('showingQrCode', false);

        $this->assertNull($user->fresh()->two_factor_secret);
    }

    public function test_an_incorrect_password_does_not_enable_two_factor(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(TwoFactorAuthentication::class)
            ->call('enableTwoFactorAuthentication')
            ->set('confirmablePassword', 'wrong-password')
            ->call('confirmPassword')
            ->assertHasErrors('confirmablePassword')
            ->assertSet('confirmingPassword', true);

        $this->assertNull($user->fresh()->two_factor_secret);
    }

    public function test_two_factor_authentication_can_be_enabled_after_confirming_the_password(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(TwoFactorAuthentication::class)
            ->call('enableTwoFactorAuthentication')
            ->set('confirmablePassword', 'password')
            ->call('confirmPassword')
            ->assertSet('confirmingPassword', false)
            ->assertSet('showingConfirmation', true);

        $user = $user->fresh();
        $this->assertNotNull($user->two_factor_secret);
        $this->assertCount(8, $user->recoveryCodes());
        $this->assertNull($user->two_factor_confirmed_at);
    }

    public function test_two_factor_authentication_can_be_confirmed_with_a_valid_code(): void
    {
        $user = User::factory()->create();

        $component = Livewire::actingAs($user)
            ->test(TwoFactorAuthentication::class)
            ->call('enableTwoFactorAuthentication')
            ->set('confirmablePassword', 'password')
            ->call('confirmPassword');

        $secret = Fortify::currentEncrypter()->decrypt($user->fresh()->two_factor_secret);
        $validCode = (new Google2FA)->getCurrentOtp($secret);

        $component->set('code', $validCode)->call('confirmTwoFactorAuthentication');

        $this->assertNotNull($user->fresh()->two_factor_confirmed_at);
    }

    public function test_an_unconfirmed_setup_can_be_cancelled_without_a_password(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(TwoFactorAuthentication::class)
            ->call('enableTwoFactorAuthentication')
            ->set('confirmablePassword', 'password')
            ->call('confirmPassword')
            ->call('cancelSetup')
            ->assertSet('showingConfirmation', false);

        $this->assertNull($user->fresh()->two_factor_secret);
    }

    public function test_viewing_recovery_codes_requires_a_password(): void
    {
        $user = User::factory()->twoFactorEnabled()->create();

        Livewire::actingAs($user)
            ->test(TwoFactorAuthentication::class)
            ->call('showRecoveryCodes')
            ->assertSet('confirmingPassword', true)
            ->assertSet('showingRecoveryCodes', false)
            ->set('confirmablePassword', 'password')
            ->call('confirmPassword')
            ->assertSet('showingRecoveryCodes', true);
    }

    /**
     * The browser can't skip the password prompt by setting what to reveal, or
     * swap the pending action (GitHub #15).
     *
     * @return array<string, array{string, mixed}>
     */
    public static function lockedState(): array
    {
        return [
            'QR code' => ['showingQrCode', true],
            'confirmation' => ['showingConfirmation', true],
            'recovery codes' => ['showingRecoveryCodes', true],
            'pending action' => ['confirmingAction', 'disable'],
            'pending arguments' => ['confirmingArguments', ['x']],
        ];
    }

    #[DataProvider('lockedState')]
    public function test_the_browser_cant_set_what_the_page_reveals(string $property, mixed $value): void
    {
        $user = User::factory()->twoFactorEnabled()->create();

        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::actingAs($user)->test(TwoFactorAuthentication::class)->set($property, $value);
    }

    /** Fortify keeps the existing secret, so enabling again would show it. */
    public function test_two_factor_cant_be_enabled_again_while_on(): void
    {
        $user = User::factory()->twoFactorEnabled()->create();
        $secret = $user->two_factor_secret;

        Livewire::actingAs($user)
            ->test(TwoFactorAuthentication::class)
            ->call('enableTwoFactorAuthentication')
            ->assertForbidden();

        $this->assertSame($secret, $user->fresh()->two_factor_secret);
    }

    /** Cancel and confirm belong to a setup in progress, not to two-factor that is already on (refused before the code is checked). */
    public function test_cancel_and_confirm_dont_work_outside_a_setup(): void
    {
        $user = User::factory()->twoFactorEnabled()->create();
        $page = Livewire::actingAs($user)->test(TwoFactorAuthentication::class);

        $page->call('cancelSetup')->assertForbidden();
        $this->assertNotNull($user->fresh()->two_factor_secret);

        Livewire::actingAs($user)->test(TwoFactorAuthentication::class)
            ->set('code', '123456')
            ->call('confirmTwoFactorAuthentication')
            ->assertForbidden();
    }

    /** A setup confirmed in another tab can't be cancelled from a tab still showing it. */
    public function test_a_stale_tab_cant_cancel_a_setup_confirmed_elsewhere(): void
    {
        $user = User::factory()->create();
        $staleTab = Livewire::actingAs($user)
            ->test(TwoFactorAuthentication::class)
            ->call('enableTwoFactorAuthentication')
            ->set('confirmablePassword', 'password')
            ->call('confirmPassword')
            ->assertSet('showingConfirmation', true);
        $user->forceFill(['two_factor_confirmed_at' => now()])->save();

        $staleTab->call('cancelSetup')->assertForbidden();

        $this->assertNotNull($user->fresh()->two_factor_secret);
    }

    public function test_recovery_codes_can_be_regenerated_after_confirming_the_password(): void
    {
        $user = User::factory()->create();

        $component = Livewire::actingAs($user)
            ->test(TwoFactorAuthentication::class)
            ->call('enableTwoFactorAuthentication')
            ->set('confirmablePassword', 'password')
            ->call('confirmPassword');

        $codesAfterFirstRegeneration = $user->fresh()->recoveryCodes();

        $component
            ->call('regenerateRecoveryCodes')
            ->set('confirmablePassword', 'password')
            ->call('confirmPassword');

        $this->assertCount(8, $codesAfterFirstRegeneration);
        $this->assertCount(8, array_diff($codesAfterFirstRegeneration, $user->fresh()->recoveryCodes()));
    }

    public function test_two_factor_authentication_can_be_disabled_after_confirming_the_password(): void
    {
        $user = User::factory()->twoFactorEnabled()->create();

        Livewire::actingAs($user)
            ->test(TwoFactorAuthentication::class)
            ->call('disableTwoFactorAuthentication')
            ->assertSet('confirmingPassword', true)
            ->set('confirmablePassword', 'password')
            ->call('confirmPassword');

        $this->assertNull($user->fresh()->two_factor_secret);
    }

    /** The password prompt can't be guessed without limit (GitHub #18). */
    public function test_too_many_wrong_passwords_at_the_prompt_are_throttled(): void
    {
        $user = User::factory()->twoFactorEnabled()->create();
        $page = Livewire::actingAs($user)->test(TwoFactorAuthentication::class)->call('showRecoveryCodes');

        foreach (range(1, 5) as $attempt) {
            $page->set('confirmablePassword', 'wrong-'.$attempt)->call('confirmPassword')->assertHasErrors(['confirmablePassword']);
        }

        $page->set('confirmablePassword', 'password')->call('confirmPassword')
            ->assertHasErrors(['confirmablePassword'])
            ->assertSee('Too many wrong passwords.')
            ->assertSet('showingRecoveryCodes', false);
    }

    public function test_the_right_password_at_the_prompt_clears_earlier_misses(): void
    {
        $user = User::factory()->twoFactorEnabled()->create();
        $page = Livewire::actingAs($user)->test(TwoFactorAuthentication::class)->call('showRecoveryCodes');

        foreach (range(1, 4) as $attempt) {
            $page->set('confirmablePassword', 'wrong-'.$attempt)->call('confirmPassword');
        }

        $page->set('confirmablePassword', 'password')->call('confirmPassword')->assertHasNoErrors();

        $this->assertFalse(PasswordChecks::tooMany($user));
    }

    public function test_an_empty_password_at_the_prompt_isnt_counted(): void
    {
        $user = User::factory()->twoFactorEnabled()->create();
        $page = Livewire::actingAs($user)->test(TwoFactorAuthentication::class)->call('showRecoveryCodes');

        foreach (range(1, 5) as $attempt) {
            $page->set('confirmablePassword', '')->call('confirmPassword');
        }

        $this->assertFalse(PasswordChecks::tooMany($user));
    }
}
