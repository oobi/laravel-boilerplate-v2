<?php

declare(strict_types=1);

namespace App\Support\Auth;

use App\Enums\LoginFallback;
use App\Models\User;
use App\Support\TwoFactor\GracePeriod;

/**
 * Whether a user who has proved who they are may be signed in, shared by every
 * way in (the password login in AuthenticateUser, Google's callback) so none
 * can skip a rule. Each refusal is a lang key, shown against the login form.
 */
final class LoginEligibility
{
    /** The reason this user may not sign in, or null when they may. */
    public static function refusal(User $user): ?string
    {
        // Deliberately the wrong-password message, so an inactive account isn't revealed.
        if (! $user->active) {
            return 'auth.inactive';
        }

        if (GracePeriod::locksOut($user)) {
            return 'auth.two_factor_locked_out';
        }

        // Somewhere to land, or with LOGIN_FALLBACK=reject refused here, before
        // they're logged in (or sent to the two-factor challenge).
        if (Destination::claimed($user) === null && LoginFallback::current() === LoginFallback::REJECT) {
            return 'auth.no_workspace';
        }

        return null;
    }
}
