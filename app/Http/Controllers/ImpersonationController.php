<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Auth\Destination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Lab404\Impersonate\Controllers\ImpersonateController;

/**
 * Impersonation start/leave, wrapping lab404's controller with two changes over
 * its config-driven defaults:
 *
 *  - `take` lands the impersonator on the *target's* own post-login destination
 *    ({@see Destination::home} — read-only, thanks to the stateless TeamDestination),
 *    so you immediately see what they'd see, instead of a fixed admin URL.
 *  - `leave` returns to where impersonation was started (remembered per session
 *    via lab404's own `leave_redirect_to` key), falling back to the impersonated
 *    user's page, then the user list (config `leave_redirect_to`).
 *
 * The two routes are split across hosts in routes/web.php: `take` on the admin
 * host (staff only), `leave` on the account host — always reachable, so an
 * impersonator can exit even when off the fenceable admin network
 * (~dev/TEAMS_DOMAINS_HOST_SPLIT.md §5). `leave` itself is lab404's, unchanged.
 */
class ImpersonationController extends ImpersonateController
{
    /** lab404's session key for a one-off leave redirect (read + forgotten by leave()). */
    private const LEAVE_REDIRECT_KEY = 'laravel-impersonate:leave_redirect_to';

    public function take(Request $request, $id, $guardName = null): RedirectResponse
    {
        // Capture the initiating page before the auth user is swapped.
        $origin = $this->originUrl($request, $id);

        $response = parent::take($request, $id, $guardName);

        // A guard failed (self / nested / not impersonatable): keep lab404's response.
        if (! $this->manager->isImpersonating()) {
            return $response;
        }

        $request->session()->put(self::LEAVE_REDIRECT_KEY, $origin);

        return redirect()->to(Destination::home($request->user()));
    }

    /**
     * Where "leave" should return the impersonator: the page they started from,
     * else — when there's no usable referer — the impersonated user's own page.
     */
    private function originUrl(Request $request, mixed $id): string
    {
        $previous = url()->previous();

        return ($previous === '' || $previous === url()->to('/'))
            ? route('users.show', $id)
            : $previous;
    }
}
