<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use App\Models\User;
use App\Support\Auth\LoginEligibility;
use Illuminate\Auth\Events\Failed;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
            $this->fail($request, $user);
        }

        if (! $user->active) {
            $this->fail($request, $user);
        }

        // The rest (2FA grace lockout, LOGIN_FALLBACK=reject) is shared with Google's callback.
        if ($refusal = LoginEligibility::refusal($user)) {
            throw ValidationException::withMessages([
                Fortify::username() => [__($refusal)],
            ]);
        }

        // Laravel's own login upgrades an outdated hash once the password checks out.
        if (config('hashing.rehash_on_login', true)) {
            Auth::guard(config('fortify.guard'))->getProvider()->rehashPasswordIfRequired($user, ['password' => $request->password]);
        }

        return $user;
    }

    /**
     * One message for a wrong password and an inactive account, so neither is
     * revealed. Laravel's Failed event still fires, as it does when Fortify
     * refuses a login itself, for anything listening (lockouts, audit).
     */
    private function fail(Request $request, ?User $user): never
    {
        event(new Failed(config('fortify.guard'), $user, [
            Fortify::username() => $request->{Fortify::username()},
            'password' => $request->password,
        ]));

        throw ValidationException::withMessages([
            Fortify::username() => [__('auth.inactive')],
        ]);
    }
}
