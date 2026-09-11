<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where a freshly-authenticated user with neither a system role nor any add-on
 * destination (e.g. a team) is sent — the one app-specific branch of the
 * post-login cascade. `config('fortify.login_fallback')`; see
 * App\Http\Responses\LoginResponse.
 */
enum LoginFallback: string
{
    /** Land on the public home page (the default for a public-facing app). */
    case HOME = 'home';

    /** No public surface: log the user back out with a "contact an admin" notice. */
    case REJECT = 'reject';

    public static function current(): self
    {
        return self::tryFrom((string) config('fortify.login_fallback', self::HOME->value)) ?? self::HOME;
    }
}
