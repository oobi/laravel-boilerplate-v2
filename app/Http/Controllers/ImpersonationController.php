<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Impersonation\StartImpersonation;
use Illuminate\Http\RedirectResponse;
use Lab404\Impersonate\Services\ImpersonateManager;

/**
 * Leaving impersonation. A POST endpoint (never GET) so the CSRF-protected
 * "stop impersonating" form in the banner is the only way to trigger it —
 * see GitHub #11. Starting impersonation is not a route at all: it happens
 * inside the CSRF-protected Livewire/Filament action via
 * {@see StartImpersonation}.
 *
 * The destination is lab404's own per-session `leave_redirect_to` (recorded by
 * StartImpersonation as the page the impersonator started from), falling back
 * to the config route when absent.
 */
class ImpersonationController extends Controller
{
    public function leave(ImpersonateManager $manager): RedirectResponse
    {
        abort_unless($manager->isImpersonating(), 403);

        $manager->leave();

        return redirect()->to($manager->getLeaveRedirectTo());
    }
}
