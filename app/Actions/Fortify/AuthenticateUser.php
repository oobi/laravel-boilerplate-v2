<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use App\Models\User;
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

        return $user;
    }

    private function fail(): never
    {
        throw ValidationException::withMessages([
            Fortify::username() => [__('auth.inactive')],
        ]);
    }
}
