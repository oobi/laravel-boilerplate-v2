<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\TwoFactor\GracePeriod;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpFoundation\Response;

/**
 * The remember-me half of the mandatory-2FA login refusal. A "remember me"
 * cookie logs the user in without going through AuthenticateUser, so this
 * applies the same GracePeriod::locksOut() check, but only on the request
 * where the cookie did the logging in (Auth::viaRemember()); every other
 * request passes straight through. Logging out also rotates the user's
 * remember token, so the cookie stops working.
 */
class EnsureRememberedUserIsNotLockedOut
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! Auth::guard('web')->viaRemember() || ! GracePeriod::locksOut($user)) {
            return $next($request);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')
            ->withErrors([Fortify::username() => __('auth.two_factor_locked_out')]);
    }
}
