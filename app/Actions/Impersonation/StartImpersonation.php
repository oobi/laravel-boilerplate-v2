<?php

declare(strict_types=1);

namespace App\Actions\Impersonation;

use App\Enums\UserAbility;
use App\Models\User;
use App\Support\Auth\Destination;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Lab404\Impersonate\Services\ImpersonateManager;

/**
 * Starts impersonation of $target by the acting user, from the CSRF-protected
 * request that invokes it (a Livewire/Filament action or a POST controller) —
 * this is why there is no GET "take" route: state-changing impersonation must
 * not ride a plain navigation (see GitHub #11).
 *
 * Re-authorizes at the write boundary via {@see UserAbility::IMPERSONATE},
 * whose policy wraps every guard lab404's own controller applied — actor may
 * impersonate, target may be impersonated, not yourself, not while already
 * impersonating — then records where "leave" should return and performs the
 * guard swap. Where to land *after* taking is the caller's decision (it holds
 * the context), deliberately kept out of here so no URL can smuggle in a
 * redirect target; see {@see Destination}.
 */
class StartImpersonation
{
    /** lab404's session key for a one-off leave redirect (read + forgotten by ImpersonationController::leave()). */
    private const LEAVE_REDIRECT_KEY = 'laravel-impersonate:leave_redirect_to';

    public function __construct(private ImpersonateManager $manager) {}

    /**
     * @param  User  $target  the user to impersonate
     * @param  string|null  $leaveRedirect  where "leave" returns the impersonator; defaults to the target's admin page
     */
    public function handle(User $target, ?string $leaveRedirect = null): void
    {
        Gate::authorize(UserAbility::IMPERSONATE->value, $target);

        session()->put(self::LEAVE_REDIRECT_KEY, $leaveRedirect ?? route('users.show', $target));

        $this->manager->take(Auth::user(), $target, config('laravel-impersonate.default_impersonator_guard'));
    }
}
