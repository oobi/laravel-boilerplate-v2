<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Livewire\TwoFactorAuthentication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Fortify;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorAuthenticationSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/admin/profile/two-factor-authentication')->assertRedirect('/login');
    }

    public function test_visiting_the_page_without_a_confirmed_password_redirects_to_confirm_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/admin/profile/two-factor-authentication')
            ->assertRedirect('/user/confirm-password');
    }

    public function test_two_factor_authentication_can_be_enabled(): void
    {
        $user = User::factory()->create();

        $this->withSession(['auth.password_confirmed_at' => time()]);

        Livewire::actingAs($user)
            ->test(TwoFactorAuthentication::class)
            ->call('enableTwoFactorAuthentication')
            ->assertSet('showingConfirmation', true);

        $user = $user->fresh();
        $this->assertNotNull($user->two_factor_secret);
        $this->assertCount(8, $user->recoveryCodes());
        $this->assertNull($user->two_factor_confirmed_at);
    }

    public function test_two_factor_authentication_can_be_confirmed_with_a_valid_code(): void
    {
        $user = User::factory()->create();
        $this->withSession(['auth.password_confirmed_at' => time()]);

        $component = Livewire::actingAs($user)
            ->test(TwoFactorAuthentication::class)
            ->call('enableTwoFactorAuthentication');

        $secret = Fortify::currentEncrypter()->decrypt($user->fresh()->two_factor_secret);
        $validCode = (new Google2FA)->getCurrentOtp($secret);

        $component->set('code', $validCode)->call('confirmTwoFactorAuthentication');

        $this->assertNotNull($user->fresh()->two_factor_confirmed_at);
    }

    public function test_recovery_codes_can_be_regenerated(): void
    {
        $user = User::factory()->create();
        $this->withSession(['auth.password_confirmed_at' => time()]);

        $component = Livewire::actingAs($user)
            ->test(TwoFactorAuthentication::class)
            ->call('enableTwoFactorAuthentication')
            ->call('regenerateRecoveryCodes');

        $codesAfterFirstRegeneration = $user->fresh()->recoveryCodes();

        $component->call('regenerateRecoveryCodes');

        $this->assertCount(8, $codesAfterFirstRegeneration);
        $this->assertCount(8, array_diff($codesAfterFirstRegeneration, $user->fresh()->recoveryCodes()));
    }

    public function test_two_factor_authentication_can_be_disabled(): void
    {
        $user = User::factory()->create();
        $this->withSession(['auth.password_confirmed_at' => time()]);

        $component = Livewire::actingAs($user)
            ->test(TwoFactorAuthentication::class)
            ->call('enableTwoFactorAuthentication');

        $this->assertNotNull($user->fresh()->two_factor_secret);

        $component->call('disableTwoFactorAuthentication');

        $this->assertNull($user->fresh()->two_factor_secret);
    }
}
