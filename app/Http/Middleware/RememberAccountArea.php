<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\AccountArea\AccountAreaRegistry;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records the area a route belongs to (`RememberAccountArea::class.':admin'`),
 * so the profile renders in that area's shell. Listed after the group's entry
 * `can:` so only a user who can enter the area is recorded. See {@see AccountAreaRegistry}.
 */
class RememberAccountArea
{
    public function handle(Request $request, Closure $next, string $area): Response
    {
        AccountAreaRegistry::remember($area);

        return $next($request);
    }
}
