<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Actions\Impersonation\StopImpersonation;
use Closure;
use Illuminate\Http\Request;
use Lab404\Impersonate\Services\ImpersonateManager;
use Symfony\Component\HttpFoundation\Response;

/**
 * Turns Fortify's logout into "stop impersonating" while impersonating. A real
 * logout would sign out the impersonated user, rotating their remember token
 * (ending their own remembered sessions), and end the impersonator's session.
 * If the impersonator can't be restored, the real logout goes ahead.
 */
class LeaveImpersonationOnLogout
{
    public function __construct(
        private ImpersonateManager $manager,
        private StopImpersonation $stopImpersonation,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $destination = $this->manager->isImpersonating() ? $this->stopImpersonation->handle() : null;

        return $destination === null ? $next($request) : redirect()->to($destination);
    }
}
