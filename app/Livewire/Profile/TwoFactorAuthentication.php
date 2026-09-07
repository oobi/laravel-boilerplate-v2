<?php

declare(strict_types=1);

namespace App\Livewire\Profile;

use App\Livewire\Concerns\ConfirmsPassword;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;
use Laravel\Fortify\Features;
use Livewire\Component;

/**
 * "Two-Factor" — the Two-Factor tab of the self-service account area. Every
 * action that sets up or tears down a second factor — enabling, disabling,
 * regenerating recovery codes, or revealing the existing codes — is guarded by
 * an inline password prompt (see the ConfirmsPassword trait); the current user
 * must re-enter their password each time.
 */
class TwoFactorAuthentication extends Component
{
    use ConfirmsPassword;

    public bool $showingQrCode = false;

    public bool $showingConfirmation = false;

    public bool $showingRecoveryCodes = false;

    public string $code = '';

    public function mount(): void
    {
        $user = Auth::user();

        // A never-confirmed setup attempt shouldn't survive past this page reload.
        if (Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm')
            && ! is_null($user->two_factor_secret)
            && is_null($user->two_factor_confirmed_at)) {
            app(DisableTwoFactorAuthentication::class)($user);
        }
    }

    public function enableTwoFactorAuthentication(): void
    {
        $this->startConfirmingPassword('enable');
    }

    public function disableTwoFactorAuthentication(): void
    {
        $this->startConfirmingPassword('disable');
    }

    public function regenerateRecoveryCodes(): void
    {
        $this->startConfirmingPassword('regenerate');
    }

    public function showRecoveryCodes(): void
    {
        $this->startConfirmingPassword('showRecoveryCodes');
    }

    /** Entering a valid TOTP code is itself proof of possession — no password prompt needed here. */
    public function confirmTwoFactorAuthentication(): void
    {
        app(ConfirmTwoFactorAuthentication::class)(Auth::user(), $this->code);

        $this->reset('code');
        $this->showingQrCode = false;
        $this->showingConfirmation = false;
        $this->showingRecoveryCodes = true;
    }

    /** Abandoning an unconfirmed setup only discards a secret the user just generated — no password prompt needed. */
    public function cancelSetup(): void
    {
        app(DisableTwoFactorAuthentication::class)(Auth::user());

        $this->resetSetupState();
    }

    public function getEnabledProperty(): bool
    {
        return ! empty(Auth::user()->two_factor_secret);
    }

    public function render(): View
    {
        return view('livewire.profile.two-factor-authentication');
    }

    protected function dispatchConfirmedAction(string $action, array $arguments): void
    {
        match ($action) {
            'enable' => $this->performEnable(),
            'disable' => $this->performDisable(),
            'regenerate' => $this->performRegenerate(),
            'showRecoveryCodes' => $this->performShowRecoveryCodes(),
            default => abort(403),
        };
    }

    protected function performEnable(): void
    {
        app(EnableTwoFactorAuthentication::class)(Auth::user());

        $this->showingQrCode = true;

        if (Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm')) {
            $this->showingConfirmation = true;
        } else {
            $this->showingRecoveryCodes = true;
        }
    }

    protected function performDisable(): void
    {
        app(DisableTwoFactorAuthentication::class)(Auth::user());

        $this->resetSetupState();
    }

    protected function performRegenerate(): void
    {
        app(GenerateNewRecoveryCodes::class)(Auth::user());

        $this->showingRecoveryCodes = true;
    }

    protected function performShowRecoveryCodes(): void
    {
        $this->showingRecoveryCodes = true;
    }

    private function resetSetupState(): void
    {
        $this->showingQrCode = false;
        $this->showingConfirmation = false;
        $this->showingRecoveryCodes = false;
    }
}
