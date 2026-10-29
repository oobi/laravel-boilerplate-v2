<?php

declare(strict_types=1);

namespace Concise\Teams\Http\Middleware;

use Closure;
use Concise\Teams\Models\TeamInvitation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs ahead of `signed` on the invitation links: a genuine link (correct
 * signature) to an invitation that has expired gets a page saying so, rather
 * than the bare 403 `signed` gives. Covers both the link's own expiry and the
 * invitation's, since links sent before expiry existed carry none. Anything
 * else passes through, so a tampered link still meets `signed`.
 */
class EnsureInvitationIsCurrent
{
    public function handle(Request $request, Closure $next): Response
    {
        $invitation = $request->route('invitation');

        if ($invitation instanceof TeamInvitation
            && URL::hasCorrectSignature($request)
            && ($invitation->isExpired() || ! URL::signatureHasNotExpired($request))) {
            return response()->view('teams::auth.invitation-expired', ['invitation' => $invitation], Response::HTTP_GONE);
        }

        return $next($request);
    }
}
