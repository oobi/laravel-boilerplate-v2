<?php

declare(strict_types=1);

namespace App\Http\Responses;

use App\Models\User;
use App\Support\Auth\Destination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;

/**
 * After registering, send the new user where they belong ({@see Destination::home})
 * rather than to `config('fortify.home')` (the public landing). For a team-less
 * registrant that's onboarding — and, while still unverified, the `verified`
 * middleware bounces them from there to the email-verification notice, which is
 * the prompt they actually need. Mirrors LoginResponse; the config file can't do
 * this itself (a `route()` call there runs before routes register).
 */
class RegisterResponse implements RegisterResponseContract
{
    public function toResponse($request): RedirectResponse|JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return $request->wantsJson()
            ? new JsonResponse('', 201)
            : redirect()->intended(Destination::home($user));
    }
}
