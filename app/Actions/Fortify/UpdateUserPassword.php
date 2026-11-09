<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use App\Support\Audit\Audit;
use App\Support\Audit\AuditCategory;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;

class UpdateUserPassword implements UpdatesUserPasswords
{
    use PasswordValidationRules;

    /** @param array<string, string> $input */
    public function update(User $user, array $input): void
    {
        Validator::make($input, [
            'current_password' => ['required', 'string', 'current_password:'.config('fortify.guard', 'web')],
            'password' => $this->passwordRules(),
        ], [
            'current_password.current_password' => __('auth.current_password_mismatch'),
        ])->validateWithBag('updatePassword');

        DB::transaction(function () use ($user, $input): void {
            $user->forceFill([
                'password' => Hash::make($input['password']),
            ])->save();

            Audit::record('user.password_changed', $user, category: AuditCategory::SECURITY);
        });
    }
}
