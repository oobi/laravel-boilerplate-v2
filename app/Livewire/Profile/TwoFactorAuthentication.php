<?php

declare(strict_types=1);

namespace App\Livewire\Profile;

use App\Livewire\Concerns\ConfirmsPassword;
use App\Livewire\Concerns\RequiresFullSession;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;
use Laravel\Fortify\Features;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * "Two-Factor": the Two-Factor tab of the self-service account area. Every
 * action that sets up or tears down a second factor (enabling, disabling,
 * regenerating recovery codes, or revealing the existing codes) is guarded by
 * an inline password prompt (see the ConfirmsPassword trait); the current user
 * must re-enter their password each time. See EditProfile for why this renders
 * in the neutral account layout rather than the admin shell.
 */
#[Layout('layouts.account')]
class TwoFactorAuthentication extends Component
{
    use ConfirmsPassword;
    use RequiresFullSession;

    /** What to reveal is decided server-side, after the password prompt: never settable from the browser. */
    #[Locked]
    public bool $showingQrCode = false;

    #[Locked]
    public bool $showingConfirmation = false;

    #[Locked]
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

    /** Not while it's on: Fortify keeps the existing secret, so this would show it again. */
    public function enableTwoFactorAuthentication(): void
    {
        abort_if($this->enabled, 403);

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

    /** Only during a setup. Entering a valid TOTP code is itself proof of possession, so no password prompt. */
    public function confirmTwoFactorAuthentication(): void
    {
        $this->ensureSettingUp();

        app(ConfirmTwoFactorAuthentication::class)(Auth::user(), $this->code);

        $this->reset('code');
        $this->showingQrCode = false;
        $this->showingConfirmation = false;
        $this->showingRecoveryCodes = true;
    }

    /** Only during a setup: abandoning it discards a secret the user just generated, so no password prompt. */
    public function cancelSetup(): void
    {
        $this->ensureSettingUp();

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
        abort_if($this->enabled, 403);

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

    /**
     * A setup this page started and that is still unconfirmed: the locked flag
     * says this page started one, the database that no other tab has since
     * confirmed it.
     */
    private function ensureSettingUp(): void
    {
        abort_unless($this->showingConfirmation && is_null(Auth::user()->two_factor_confirmed_at), 403);
    }

    private function resetSetupState(): void
    {
        $this->showingQrCode = false;
        $this->showingConfirmation = false;
        $this->showingRecoveryCodes = false;
    }
}
