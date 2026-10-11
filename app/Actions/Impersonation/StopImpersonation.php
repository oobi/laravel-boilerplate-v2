<?php

declare(strict_types=1);

namespace App\Actions\Impersonation;

use Lab404\Impersonate\Services\ImpersonateManager;

/**
 * Ends impersonation and returns where the impersonator goes next: the page
 * {@see StartImpersonation} recorded, or the config route when absent. lab404
 * swaps the guards quietly, so the impersonated user's remember token is left
 * alone. Shared by the banner's leave route and by logout while impersonating.
 */
class StopImpersonation
{
    public function __construct(private ImpersonateManager $manager) {}

    /** The leave destination, or null when lab404 could not restore the impersonator. */
    public function handle(): ?string
    {
        if (! $this->manager->leave()) {
            return null;
        }

        return $this->manager->getLeaveRedirectTo();
    }
}
