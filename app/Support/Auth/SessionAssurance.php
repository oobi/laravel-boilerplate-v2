<?php

declare(strict_types=1);

namespace App\Support\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Whether the person in this session proved they are the account holder. An
 * impersonator never did, so may not manage the account's own security:
 * email, password, two-factor. Add-ons with weaker sign-ins add their own
 * case here. See DenyLowAssuranceSessions.
 */
final class SessionAssurance
{
    public static function isLow(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->isImpersonated();
    }
}
