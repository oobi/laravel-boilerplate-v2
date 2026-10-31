<?php

declare(strict_types=1);

namespace App\Support\AccountArea;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Auth\MustVerifyEmail;

/**
 * Which area (admin, a team, ...) the user was last in, so account pages
 * (profile, password, two-factor) render inside that area's shell instead of
 * taking the user out of it. An area's pages call remember(); `layouts.account`
 * asks shellFor(), whose resolver re-checks access on every render and returns
 * null once it's gone, so the page falls back to the sidebar-less shell. Core
 * registers `admin`; add-ons register their own from their provider (the teams
 * tier's `team`), so core stays area-agnostic. Last area visited wins, across tabs.
 * An unverified user gets no area shell: every area route needs `verified`, so
 * its sidebar would only lead to the verify-email notice.
 */
final class AccountAreaRegistry
{
    private const string SESSION_KEY = 'account_area';

    /** @var array<string, Closure(User, ?string): ?AccountShell> */
    private static array $resolvers = [];

    /**
     * @param  Closure(User, ?string): ?AccountShell  $resolver  Given the user and the remembered id, the shell, or null if they may no longer use it.
     */
    public static function register(string $area, Closure $resolver): void
    {
        self::$resolvers[$area] = $resolver;
    }

    /** Record the area the user is in now (and which one, e.g. a team id). */
    public static function remember(string $area, int|string|null $id = null): void
    {
        session()->put(self::SESSION_KEY, ['area' => $area, 'id' => $id === null ? null : (string) $id]);
    }

    /** The remembered area's shell for this user, or null for the sidebar-less one. */
    public static function shellFor(User $user): ?AccountShell
    {
        if ($user instanceof MustVerifyEmail && ! $user->hasVerifiedEmail()) {
            return null;
        }

        $remembered = session(self::SESSION_KEY);

        if (! is_array($remembered) || ! isset(self::$resolvers[$remembered['area'] ?? null])) {
            return null;
        }

        return self::$resolvers[$remembered['area']]($user, $remembered['id'] ?? null);
    }

    /** Drop all resolvers, for tests. */
    public static function flush(): void
    {
        self::$resolvers = [];
    }
}
