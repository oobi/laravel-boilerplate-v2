<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Support\Auth\PasswordChecks;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;

/**
 * Inline "sudo" confirmation for sensitive Livewire actions.
 *
 * A guarded action first opens a password prompt; the work runs only once the
 * current user re-enters their own password, which is verified fresh every time
 * (no shared confirmation window). The consuming component maps the pending
 * action key to a protected method via {@see dispatchConfirmedAction()}, so the
 * frontend can never invoke the guarded work without supplying a valid password.
 */
trait ConfirmsPassword
{
    public bool $confirmingPassword = false;

    public string $confirmablePassword = '';

    /** The pending action and its arguments are set server-side only, by startConfirmingPassword(). */
    #[Locked]
    public ?string $confirmingAction = null;

    /** @var array<int, string> */
    #[Locked]
    public array $confirmingArguments = [];

    /**
     * Open the password prompt for a named action, deferring it until
     * {@see confirmPassword()} succeeds.
     *
     * @param  array<int, string>  $arguments
     */
    public function startConfirmingPassword(string $action, array $arguments = []): void
    {
        $this->resetErrorBag('confirmablePassword');

        $this->confirmingPassword = true;
        $this->confirmablePassword = '';
        $this->confirmingAction = $action;
        $this->confirmingArguments = $arguments;
    }

    /** Dismissing the modal from the client (Esc / backdrop / ✕) flips this off via wire:model: clean up to match stopConfirmingPassword(). */
    public function updatedConfirmingPassword(bool $value): void
    {
        if (! $value) {
            $this->stopConfirmingPassword();
        }
    }

    public function confirmPassword(): void
    {
        $this->resetErrorBag('confirmablePassword');
        $user = Auth::user();

        if (PasswordChecks::tooMany($user)) {
            $this->addError('confirmablePassword', PasswordChecks::throttledMessage($user));

            return;
        }

        if (! PasswordChecks::passes($user, $this->confirmablePassword)) {
            $this->addError('confirmablePassword', __('auth.password'));

            return;
        }

        $action = $this->confirmingAction;
        $arguments = $this->confirmingArguments;

        $this->stopConfirmingPassword();

        if ($action !== null) {
            $this->dispatchConfirmedAction($action, $arguments);
        }
    }

    public function stopConfirmingPassword(): void
    {
        $this->confirmingPassword = false;
        $this->confirmablePassword = '';
        $this->confirmingAction = null;
        $this->confirmingArguments = [];
    }

    /** Whether the prompt is currently open for this specific action (used to place the inline field). */
    public function isConfirmingPasswordFor(string $action, array $arguments = []): bool
    {
        return $this->confirmingPassword
            && $this->confirmingAction === $action
            && $this->confirmingArguments === $arguments;
    }

    /**
     * Run the just-confirmed action. Implementations MUST map $action to a
     * protected method with an explicit match(), never a dynamic
     * `$this->{$action}()` call, so a crafted request cannot use the confirm
     * flow to invoke arbitrary component methods.
     *
     * @param  array<int, string>  $arguments
     */
    abstract protected function dispatchConfirmedAction(string $action, array $arguments): void;
}
