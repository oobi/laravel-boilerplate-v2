<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Impersonation\StopImpersonation;
use App\Http\Responses\NoWorkspaceResponse;
use App\Support\Auth\Destination;
use Filament\Notifications\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The `home` route of a login-only app, which has no public page (bp:make-login-only
 * points `/` here). A guest goes to login and a signed-in user to where they
 * belong. Every last-resort redirect (`route('home')`, e.g. a remember-me user
 * opening a guest page) ends here, so a user nothing claims is turned away with
 * the "contact an admin" notice rather than left on a page that isn't there. An
 * impersonated session is handed back to the impersonator instead, with a
 * notice saying why: a logout would end the impersonator's own session.
 */
class LoginOnlyHomeController extends Controller
{
    public function __invoke(Request $request, StopImpersonation $stopImpersonation): RedirectResponse|JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('login');
        }

        if ($destination = Destination::claimed($user)) {
            return redirect()->to($destination);
        }

        if ($user->isImpersonated()) {
            $destination = $stopImpersonation->handle();

            if ($destination !== null) {
                Notification::make()
                    ->title(__('admin.impersonation_ended_no_access', ['name' => $user->name]))
                    ->warning()
                    ->send();
            }

            return redirect()->to($destination ?? route('login'));
        }

        return (new NoWorkspaceResponse)->toResponse($request);
    }
}
