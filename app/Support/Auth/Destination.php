<?php

declare(strict_types=1);

namespace App\Support\Auth;

use App\Models\User;

/**
 * Where a user belongs: the admin dashboard for a system role, else the first
 * add-on destination that claims them via {@see LoginRedirectRegistry} (e.g.
 * the teams tier sends a member straight to their team). Shared by
 * App\Http\Responses\LoginResponse (after a password or two-factor login),
 * App\Actions\Fortify\AuthenticateUser (which refuses a user neither claims
 * under LoginFallback::REJECT, before logging them in), the guest-page redirect
 * for a signed-in user (bootstrap/app.php) and `layouts.account` (the profile
 * pages' "home"/back link), so all agree on where a user belongs.
 */
final class Destination
{
    /** The admin dashboard or the first add-on destination that claims this user, or null if neither. */
    public static function claimed(User $user): ?string
    {
        if ($user->canAccessAdmin()) {
            return route('dashboard');
        }

        return LoginRedirectRegistry::resolve($user);
    }

    /** claimed(), falling back to the public landing page — always returns somewhere to go. */
    public static function home(User $user): string
    {
        return self::claimed($user) ?? route('home');
    }
}
