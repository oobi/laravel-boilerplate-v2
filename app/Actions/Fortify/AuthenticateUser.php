<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;

/**
 * Bound via `Fortify::authenticateUsing()`. Same vague error for wrong
 * credentials AND a deactivated account, so a login attempt can't be used to
 * enumerate which emails are registered or suspended.
 */
class AuthenticateUser
{
    public function __invoke($request): ?User
    {
        $user = User::where(Fortify::username(), $request->{Fortify::username()})->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            $this->fail();
        }

        if (config('auth.block_inactive_users', true) && ! $user->active) {
            $this->fail();
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
