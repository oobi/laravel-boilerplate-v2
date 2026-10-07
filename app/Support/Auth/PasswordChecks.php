<?php

declare(strict_types=1);

namespace App\Support\Auth;

use App\Models\User;
use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Wrong passwords typed inside a signed-in session (the confirm-password
 * prompt, the current password on Change password and on an email change),
 * counted per person across all of them: a hijacked or unattended session
 * can't guess the password without limit.
 */
final class PasswordChecks
{
    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 60;

    public static function tooMany(User $user): bool
    {
        return RateLimiter::tooManyAttempts(self::key($user), self::MAX_ATTEMPTS);
    }

    /** The message for someone who has had too many tries. */
    public static function throttledMessage(User $user): string
    {
        return __('auth.password_throttle', ['seconds' => RateLimiter::availableIn(self::key($user))]);
    }

    public static function failed(User $user): void
    {
        RateLimiter::hit(self::key($user), self::DECAY_SECONDS);
    }

    public static function clear(User $user): void
    {
        RateLimiter::clear(self::key($user));
    }

    /**
     * Whether this is the person's password, counting a miss and clearing on a
     * match; an empty one is refused without counting. Callers check tooMany()
     * first, to say why they refuse.
     */
    public static function passes(User $user, string $password): bool
    {
        if ($password === '') {
            return false;
        }

        if (! Hash::check($password, $user->password)) {
            self::failed($user);

            return false;
        }

        self::clear($user);

        return true;
    }

    /**
     * A validation rule for a current-password field that counts here:
     * refused while throttled, a miss counted, a match clears. In Filament,
     * wrap it: `->rule(fn (): Closure => PasswordChecks::rule())`.
     */
    public static function rule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            /** @var User $user */
            $user = Auth::user();

            if (self::tooMany($user)) {
                $fail(self::throttledMessage($user));

                return;
            }

            if (! self::passes($user, (string) $value)) {
                $fail(__('auth.password'));
            }
        };
    }

    private static function key(User $user): string
    {
        return 'password-check:'.$user->getKey();
    }
}
