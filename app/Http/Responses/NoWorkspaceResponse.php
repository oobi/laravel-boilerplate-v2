<?php

declare(strict_types=1);

namespace App\Http\Responses;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Fortify;

/**
 * Turns away a signed-in user the app has nowhere to send (no system role, no
 * add-on destination, and no public home): logs them out and shows the
 * "contact an admin" notice on the login form. Shared by LoginResponse's
 * reject backstop and the login-only root (LoginOnlyHomeController).
 */
class NoWorkspaceResponse implements Responsable
{
    public function toResponse($request): RedirectResponse|JsonResponse
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
