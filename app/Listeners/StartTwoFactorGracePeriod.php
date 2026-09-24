<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\User;
use App\Support\TwoFactor\GracePeriod;
use Illuminate\Auth\Events\Login;

/**
 * Starts a subject user's mandatory-2FA grace clock at their first login while
 * subject. Login fires for password and remember-me logins alike, so either
 * way in starts the runway.
 */
class StartTwoFactorGracePeriod
{
    public function handle(Login $event): void
    {
        /** @var User $user */
        $user = $event->user;

        if (GracePeriod::applies($user)) {
            GracePeriod::start($user);
        }
    }
}
