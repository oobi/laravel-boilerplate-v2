<?php

declare(strict_types=1);

namespace App\Http\Responses;

use App\Models\User;
use App\Support\Auth\Destination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Laravel\Fortify\Contracts\VerifyEmailResponse as VerifyEmailResponseContract;

/**
 * After verifying their email, land the (now verified) user in the app — their
 * team, the admin dashboard, or onboarding ({@see Destination::home}) — not on
 * the public landing that `config('fortify.home')` would give. See
 * ~dev/TEAMS_DOMAINS_HOST_SPLIT.md §3.1.
 */
class VerifyEmailResponse implements VerifyEmailResponseContract
{
    public function toResponse($request): RedirectResponse|JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return $request->wantsJson()
            ? new JsonResponse('', 204)
            : redirect()->intended(Destination::home($user).'?verified=1');
    }
}
