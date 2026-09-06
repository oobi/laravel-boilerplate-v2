<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Lab404\Impersonate\Services\ImpersonateManager;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpFoundation\Response;

/**
 * Revoke access mid-session when a user is deactivated after they signed in.
 *
 * During impersonation both accounts must remain available. Ending the
 * session discards the original identity too; it never restores the admin.
 * The web group also applies this check to Livewire updates.
 */
class EnsureAccountIsActive
{
    public function __construct(private ImpersonateManager $impersonation) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('auth.block_inactive_users', true)) {
            return $next($request);
        }

        $user = $request->user();

        if ($user && ! $user->active) {
            return $this->logout($request);
        }

        if ($this->impersonation->isImpersonating()) {
            $impersonator = User::find($this->impersonation->getImpersonatorId());

            if (! $user || ! $impersonator?->active) {
                return $this->logout($request);
            }
        }

        if ($request->routeIs('two-factor.login', 'two-factor.login.store')
            && $request->session()->has('login.id')) {
            return $this->handlePendingChallenge($request, $next);
        }

        return $next($request);
    }

    private function logout(Request $request): Response
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')
            ->withErrors([Fortify::username() => __('auth.inactive')]);
    }

    private function handlePendingChallenge(Request $request, Closure $next): Response
    {
        $user = User::find($request->session()->get('login.id'));

        if ($user && $user->active) {
            return $next($request);
        }

        $request->session()->forget(['login.id', 'login.remember']);

        return redirect()->route('login')
            ->withErrors([Fortify::username() => __('auth.inactive')]);
    }
}
