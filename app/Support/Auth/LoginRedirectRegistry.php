<?php

declare(strict_types=1);

namespace App\Support\Auth;

use App\Models\User;
use Closure;

/**
 * Add-on-contributed post-login destinations, so core routing stays
 * area-agnostic (mirrors NavRegistry / AccountMenuRegistry). LoginResponse
 * resolves a system role first, then asks the registered resolvers in order;
 * the first to return a URL wins. The teams tier registers one from its
 * provider to send team users to their team. See App\Http\Responses\LoginResponse.
 */
class LoginRedirectRegistry
{
    /** @var array<int, Closure(User): ?string> */
    private static array $resolvers = [];

    /**
     * @param  Closure(User): ?string  $resolver  Returns a URL for this user, or null to defer.
     */
    public static function register(Closure $resolver): void
    {
        self::$resolvers[] = $resolver;
    }

    /** The first resolver's URL for this user, or null if none claims them. */
    public static function resolve(User $user): ?string
    {
        // A plain loop (not a collect() chain) so a resolver's DB work stops at the first match.
        foreach (self::$resolvers as $resolver) {
            $url = $resolver($user);

            if ($url !== null && $url !== '') {
                return $url;
            }
        }

        return null;
    }

    /** Drop all resolvers — for tests that assert the fallback path. */
    public static function flush(): void
    {
        self::$resolvers = [];
    }
}
