<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Closure;

/**
 * Add-on-contributed reasons a user can't be deleted yet (mirrors
 * LoginRedirectRegistry). UserObserver refuses any deletion one of them
 * blocks, super admins included, and the Users screen shows the reason before
 * anyone confirms. The teams tier registers one: a team's primary owner can't
 * be deleted until the team is transferred or deleted (GitHub oobi#28).
 */
class UserDeletionGuards
{
    /** @var list<Closure(User, bool): ?string> */
    private static array $guards = [];

    /**
     * @param  Closure(User $user, bool $permanently): ?string  $guard  Returns why the user can't be deleted, or null.
     */
    public static function register(Closure $guard): void
    {
        self::$guards[] = $guard;
    }

    /** The first reason this user can't be deleted (or permanently deleted), or null. */
    public static function reasonFor(User $user, bool $permanently = false): ?string
    {
        // A plain loop (not a collect() chain) so a guard's DB work stops at the first refusal.
        foreach (self::$guards as $guard) {
            $reason = $guard($user, $permanently);

            if ($reason !== null && $reason !== '') {
                return $reason;
            }
        }

        return null;
    }

    /** Drop all guards, for tests. */
    public static function flush(): void
    {
        self::$guards = [];
    }
}
