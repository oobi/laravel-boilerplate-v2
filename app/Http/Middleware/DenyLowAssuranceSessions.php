<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Auth\SessionAssurance;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps impersonated sessions off account-security routes
 * (the profile pages and Fortify's two-factor endpoints). Livewire updates
 * skip route middleware, so the profile components also use RequiresFullSession.
 */
class DenyLowAssuranceSessions
{
    /**
     * Whether a Fortify route is one this guards: every two-factor endpoint
     * but the login challenge, plus passkey management and the profile and
     * password routes should those features be switched on (all are off in
     * config/fortify.php). The profile pages call the actions directly, so
     * this is defence in depth.
     */
    public static function guardsFortifyRoute(?string $name): bool
    {
        if ($name === null) {
            return false;
        }

        return (str_starts_with($name, 'two-factor.') && ! str_starts_with($name, 'two-factor.login'))
            || in_array($name, ['passkey.registration-options', 'passkey.store', 'passkey.destroy', 'user-profile-information.update', 'user-password.update'], true);
    }

    public function handle(Request $request, Closure $next): Response
    {
        abort_if(SessionAssurance::isLow(), 403);

        return $next($request);
    }
}
