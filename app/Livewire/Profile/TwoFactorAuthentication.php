<?php

declare(strict_types=1);

namespace App\Livewire\Profile;

use App\Livewire\Concerns\ConfirmsPassword;
use App\Livewire\Concerns\RequiresFullSession;
use App\Support\Auth\GoogleLogin;
use App\Support\TwoFactor\GracePeriod;
use Filament\Notifications\Notification;
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
 * must re-enter their password each time. Linking and unlinking a Google
 * account (the other way in, see GoogleLoginController) sit here too, behind
 * the same prompt. See EditProfile for why this renders
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

    /** Not while a role requires it (#40): an admin can still force-disable. */
    public function disableTwoFactorAuthentication(): void
    {
        abort_if(GracePeriod::mandated(Auth::user()), 403);

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

    /** Adds a way into the account, so it asks for the password like 2FA changes do. */
    public function linkGoogle(): void
    {
        abort_unless(GoogleLogin::enabled(), 404);

        $this->startConfirmingPassword('linkGoogle');
    }

    public function unlinkGoogle(): void
    {
        $this->startConfirmingPassword('unlinkGoogle');
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
        unset($this->enabled);

        $this->resetSetupState();
    }

    /**
     * Livewire memoizes this for the whole request, so every method that turns
     * 2FA on or off forgets it (`unset($this->enabled)`). Otherwise a value read
     * before the change (performEnable()'s guard) renders the page as if
     * nothing happened: no QR code, no code field.
     */
    public function getEnabledProperty(): bool
    {
        return ! empty(Auth::user()->two_factor_secret);
    }

    public function render(): View
    {
        return view('livewire.profile.two-factor-authentication', [
            'mayDisable' => ! GracePeriod::mandated(Auth::user()),
            'googleEnabled' => GoogleLogin::enabled(),
            'googleAccount' => Auth::user()->googleAccount,
        ]);
    }

    protected function dispatchConfirmedAction(string $action, array $arguments): void
    {
        match ($action) {
            'enable' => $this->performEnable(),
            'disable' => $this->performDisable(),
            'regenerate' => $this->performRegenerate(),
            'showRecoveryCodes' => $this->performShowRecoveryCodes(),
            'linkGoogle' => $this->performLinkGoogle(),
            'unlinkGoogle' => $this->performUnlinkGoogle(),
            default => abort(403),
        };
    }

    protected function performEnable(): void
    {
        abort_if($this->enabled, 403);

        app(EnableTwoFactorAuthentication::class)(Auth::user());
        unset($this->enabled);

        $this->showingQrCode = true;

        if (Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm')) {
            $this->showingConfirmation = true;
        } else {
            $this->showingRecoveryCodes = true;
        }
    }

    protected function performDisable(): void
    {
        abort_if(GracePeriod::mandated(Auth::user()), 403);

        app(DisableTwoFactorAuthentication::class)(Auth::user());
        unset($this->enabled);

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
     * Off to Google, marking the link in the session for the callback to
     * finish. Straight from here, so the password just checked opens no
     * shared confirmation window (Fortify's 2FA routes honour one).
     */
    protected function performLinkGoogle(): void
    {
        abort_unless(GoogleLogin::enabled(), 404);

        session()->put(GoogleLogin::LINKING_KEY, Auth::id());

        $this->redirect(GoogleLogin::driver()->redirect()->getTargetUrl());
    }

    protected function performUnlinkGoogle(): void
    {
        Auth::user()->googleAccount()->delete();

        Notification::make()->title(__('auth.google_unlinked'))->success()->send();
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
