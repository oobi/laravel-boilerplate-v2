<?php

declare(strict_types=1);

namespace Tests\Feature\Profile;

use App\Livewire\Profile\TwoFactorAuthentication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Fortify;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/** The Two-Factor tab of the profile area: route access, status, and the guarded setup/teardown flow. */
class TwoFactorAuthenticationSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/admin/profile/two-factor')->assertRedirect('/login');
    }

    public function test_users_without_admin_access_are_forbidden(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/admin/profile/two-factor')->assertForbidden();
    }

    public function test_admin_users_can_view_the_two_factor_page_and_see_the_disabled_status(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->get('/admin/profile/two-factor')
            ->assertOk()
            ->assertSee(__('admin.two_factor_authentication'))
            ->assertSee(__('admin.disabled'));
    }

    public function test_an_enabled_users_status_reads_enabled(): void
    {
        $admin = User::factory()->superAdmin()->twoFactorEnabled()->create();

        $this->actingAs($admin)->get('/admin/profile/two-factor')->assertSee(__('admin.enabled'));
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
}
