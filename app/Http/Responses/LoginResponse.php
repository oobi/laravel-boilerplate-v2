<?php

declare(strict_types=1);

namespace App\Http\Responses;

use App\Enums\LoginFallback;
use App\Models\User;
use App\Support\Auth\LoginRedirectRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Fortify;

/**
 * Where a user lands after logging in. The cascade is fixed: a system role goes
 * to the admin dashboard, otherwise the first add-on resolver wins (the teams
 * tier sends team users to their team area). Only the tail — a user with
 * neither a system role nor an add-on destination — is configurable via
 * `config('fortify.login_fallback')`: the public home, or, for a login-only
 * backoffice with no public page, rejected outright.
 */
class LoginResponse implements LoginResponseContract
{
    public function toResponse($request): RedirectResponse|JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $target = $this->destinationFor($user);

        if ($target === null) {
            return $this->reject($request);
        }

        return $request->wantsJson()
            ? new JsonResponse(['two_factor' => false])
            : redirect()->intended($target);
    }

    /** The post-login URL for this user, or null when they should be rejected. */
    private function destinationFor(User $user): ?string
    {
        if ($user->canAccessAdmin()) {
            return route('dashboard');
        }

        if ($url = LoginRedirectRegistry::resolve($user)) {
            return $url;
        }

        return LoginFallback::current() === LoginFallback::REJECT ? null : route('home');
    }

    /** Log the user back out (no home to send them to) with a "contact an admin" notice. */
    private function reject($request): RedirectResponse|JsonResponse
    {
        Auth::guard()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->wantsJson()) {
            return new JsonResponse(['message' => __('auth.no_workspace')], 403);
        }

        return redirect()->route('login')->withErrors([
            Fortify::username() => __('auth.no_workspace'),
        ]);
    }
}
