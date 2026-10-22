<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use App\Models\User;
use App\Support\Auth\GoogleLogin;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

class ResetUserPassword implements ResetsUserPasswords
{
    use PasswordValidationRules;

    /** @param array<string, string> $input */
    public function reset(User $user, array $input): void
    {
        Validator::make($input, [
            'password' => $this->passwordRules(),
            'email' => [
                function ($attribute, $value, $fail) use ($user): void {
                    if (! $user->active) {
                        $fail(__('auth.inactive'));
                    }
                },
            ],
        ])->validate();

        $user->forceFill([
            'password' => Hash::make($input['password']),
        ])->save();

        // A recovery: a Google link someone else made must not outlive it (#61).
        GoogleLogin::unlink($user);
    }
}
