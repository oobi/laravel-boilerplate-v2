<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\User;
use App\Support\TwoFactor\GracePeriod;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;

/**
 * Confirming 2FA ends the mandate, so its grace clock is cleared: if 2FA is
 * later disabled and the mandate applies again, it starts a full window
 * instead of finding a stale, expired one (GitHub #40).
 */
class ClearTwoFactorGracePeriod
{
    public function handle(TwoFactorAuthenticationConfirmed $event): void
    {
        /** @var User $user */
        $user = $event->user;

        GracePeriod::clearIfNotRequired($user->fresh() ?? $user);
    }
}
