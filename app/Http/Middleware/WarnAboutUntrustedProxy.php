<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Makes a missing TRUSTED_PROXIES visible. Behind an untrusted proxy every
 * visitor shares the proxy's address, so one source can trip the login,
 * sign-up and reset ceilings for everyone, silently. A request carrying
 * X-Forwarded-For from an untrusted caller logs a warning, at most once an
 * hour. See config/trustedproxy.php.
 */
class WarnAboutUntrustedProxy
{
    public const CACHE_KEY = 'untrusted-proxy-warned';

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->headers->has('X-Forwarded-For') && ! $request->isFromTrustedProxy() && Cache::add(self::CACHE_KEY, true, now()->addHour())) {
            Log::warning('A request from '.$request->ip().' carried X-Forwarded-For but that address is not a trusted proxy, so every visitor behind it shares one address and one rate limit. If the app sits behind a proxy or load balancer, set TRUSTED_PROXIES (config/trustedproxy.php). If not, a client sent the header itself and this can be ignored.');
        }

        return $next($request);
    }
}
