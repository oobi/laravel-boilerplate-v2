<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Support\Auth\SessionAssurance;

/**
 * Account-security pages: refuse impersonated sessions on every request,
 * Livewire updates included. The route group carries DenyLowAssuranceSessions,
 * which Livewire also re-runs on updates (it is registered as persistent
 * middleware); this trait keeps the rule on the component itself.
 */
trait RequiresFullSession
{
    public function bootRequiresFullSession(): void
    {
        abort_if(SessionAssurance::isLow(), 403);
    }
}
