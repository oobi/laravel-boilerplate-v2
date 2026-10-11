<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Impersonation\StartImpersonation;
use App\Actions\Impersonation\StopImpersonation;
use Illuminate\Http\RedirectResponse;
use Lab404\Impersonate\Services\ImpersonateManager;

/**
 * Leaving impersonation. A POST endpoint (never GET) so the CSRF-protected
 * "stop impersonating" form in the banner is the only way to trigger it —
 * see GitHub #11. Starting impersonation is not a route at all: it happens
 * inside the CSRF-protected Livewire/Filament action via
 * {@see StartImpersonation}.
 *
 * {@see StopImpersonation} does the leaving and picks the destination.
 */
class ImpersonationController extends Controller
{
    public function leave(ImpersonateManager $manager, StopImpersonation $stopImpersonation): RedirectResponse
    {
        abort_unless($manager->isImpersonating(), 403);

        return redirect()->to($stopImpersonation->handle() ?? url()->previous());
    }
}
