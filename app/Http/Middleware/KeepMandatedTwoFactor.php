<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\TwoFactor\GracePeriod;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fortify's own disable endpoint, held to the same rule as the profile page: a
 * user whose role requires 2FA can't turn it off (an admin can force-disable).
 * Otherwise confirming then disabling would start a fresh grace window every
 * time (GitHub #40). Attached in AppServiceProvider::registerFortify().
 */
class KeepMandatedTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_if($user instanceof User && GracePeriod::mandated($user), 403, __('admin.two_factor_required_cannot_disable'));

        return $next($request);
    }
}
