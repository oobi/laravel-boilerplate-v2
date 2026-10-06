<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Support\Auth\SessionAssurance;

/**
 * Account-security pages: refuse impersonated sessions on every request,
 * Livewire updates included (they skip route middleware). The route group
 * carries DenyLowAssuranceSessions for the page load.
 */
trait RequiresFullSession
{
    public function bootRequiresFullSession(): void
    {
        abort_if(SessionAssurance::isLow(), 403);
    }
}
