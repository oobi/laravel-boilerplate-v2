<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use App\Enums\LoginFallback;
use App\Models\User;
use App\Support\Auth\Destination;
use App\Support\TwoFactor\GracePeriod;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;

/**
 * Bound via `Fortify::authenticateUsing()`. Same vague error for wrong
 * credentials AND a deactivated account, so a login attempt can't be used to
 * enumerate which emails are registered or suspended.
 *
 * A user locked out by the mandatory-2FA grace period gets a specific message
 * instead: that check runs only after the password is verified, so it tells
 * nothing to someone who doesn't already hold the credentials. Remember-me
 * logins skip this action; EnsureRememberedUserIsNotLockedOut covers them.
 * Likewise a user with nowhere to land under LOGIN_FALLBACK=reject is told so
 * only after the password checks out.
 */
class AuthenticateUser
{
    public function __invoke($request): ?User
    {
        $user = User::where(Fortify::username(), $request->{Fortify::username()})->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            $this->fail();
        }

        if (! $user->active) {
            $this->fail();
        }

        if (GracePeriod::locksOut($user)) {
            throw ValidationException::withMessages([
                Fortify::username() => [__('auth.two_factor_locked_out')],
            ]);
        }

        // Somewhere to land, or with LOGIN_FALLBACK=reject refused here, before
        // they're logged in (or sent to the two-factor challenge).
        if (Destination::claimed($user) === null && LoginFallback::current() === LoginFallback::REJECT) {
            throw ValidationException::withMessages([
                Fortify::username() => [__('auth.no_workspace')],
            ]);
        }

        return $user;
    }

    private function fail(): never
    {
        throw ValidationException::withMessages([
            Fortify::username() => [__('auth.inactive')],
        ]);
    }
}
