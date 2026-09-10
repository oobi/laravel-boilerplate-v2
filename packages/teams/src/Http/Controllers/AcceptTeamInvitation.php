<?php

declare(strict_types=1);

namespace Concise\Teams\Http\Controllers;

use App\Models\User;
use Concise\Teams\Actions\AcceptInvitation;
use Concise\Teams\Models\TeamInvitation;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * The signed accept link from the invitation email. The signature (route
 * middleware) proves the link is ours; AcceptInvitation proves the signed-in
 * account is the invitee. A guest is routed by whether the invited email
 * already has an account: sign in (and come straight back), or create one
 * through the invitation's own registration page — which works whether or
 * not public registration is enabled, since the invitation is the
 * authorization.
 */
class AcceptTeamInvitation
{
    public function __invoke(Request $request, TeamInvitation $invitation, AcceptInvitation $accept): RedirectResponse
    {
        if ($request->user() === null) {
            return $this->routeGuest($request, $invitation);
        }

        $team = $accept($invitation, $request->user());

        Notification::make()
            ->title(team_trans('invitations.joined', ['name' => $team->name]))
            ->success()
            ->send();

        return redirect()->route('team.dashboard', ['team' => $team->slug]);
    }

    private function routeGuest(Request $request, TeamInvitation $invitation): RedirectResponse
    {
        if (User::query()->where('email', $invitation->email)->exists()) {
            // Come back here after signing in (the full signed URL is kept as the intended one).
            return redirect()
                ->guest(route('login'))
                ->with('status', team_trans('invitations.sign_in_first', [
                    'email' => $invitation->email,
                    'name' => $invitation->team->name,
                ]));
        }

        return redirect()->to(URL::signedRoute('team.invitations.register', ['invitation' => $invitation]));
    }
}
