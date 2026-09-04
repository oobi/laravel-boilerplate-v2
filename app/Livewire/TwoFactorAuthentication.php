<?php

declare(strict_types=1);

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;
use Laravel\Fortify\Features;
use Livewire\Component;

/**
 * Self-service 2FA settings, mounted behind the `password.confirm` middleware
 * (see routes/web.php) rather than Jetstream's per-action confirm-password
 * modal — the whole page already requires a freshly-confirmed password.
 */
class TwoFactorAuthentication extends Component
{
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

    public function enableTwoFactorAuthentication(EnableTwoFactorAuthentication $enable): void
    {
        $enable(Auth::user());

        $this->showingQrCode = true;

        if (Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm')) {
            $this->showingConfirmation = true;
        } else {
            $this->showingRecoveryCodes = true;
        }
    }

    public function confirmTwoFactorAuthentication(ConfirmTwoFactorAuthentication $confirm): void
    {
        $confirm(Auth::user(), $this->code);

        $this->reset('code');
        $this->showingQrCode = false;
        $this->showingConfirmation = false;
        $this->showingRecoveryCodes = true;
    }

    public function showRecoveryCodes(): void
    {
        $this->showingRecoveryCodes = true;
    }

    public function regenerateRecoveryCodes(GenerateNewRecoveryCodes $generate): void
    {
        $generate(Auth::user());

        $this->showingRecoveryCodes = true;
    }

    public function disableTwoFactorAuthentication(DisableTwoFactorAuthentication $disable): void
    {
        $disable(Auth::user());

        $this->showingQrCode = false;
        $this->showingConfirmation = false;
        $this->showingRecoveryCodes = false;
    }

    public function getEnabledProperty(): bool
    {
        return ! empty(Auth::user()->two_factor_secret);
    }

    public function render(): View
    {
        return view('livewire.two-factor-authentication')
            ->layout('layouts.public');
    }
}
