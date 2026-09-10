<?php

declare(strict_types=1);

namespace Concise\Teams\Http\Controllers;

use Concise\Teams\Actions\AcceptInvitation;
use Concise\Teams\Models\TeamInvitation;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The signed accept link from the invitation email. The signature (route
 * middleware) proves the link came from us; AcceptInvitation proves the
 * signed-in account is the invitee. Guests are sent to log in first and
 * return here afterwards (auth middleware keeps the signed URL as intended).
 */
class AcceptTeamInvitation
{
    public function __invoke(Request $request, TeamInvitation $invitation, AcceptInvitation $accept): RedirectResponse
    {
        $team = $accept($invitation, $request->user());

        Notification::make()
            ->title(__('You’ve joined :team.', ['team' => $team->name]))
            ->success()
            ->send();

        return redirect()->route('team.dashboard', ['team' => $team->slug]);
    }
}
