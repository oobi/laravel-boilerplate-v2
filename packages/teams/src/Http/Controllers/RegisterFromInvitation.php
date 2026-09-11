<?php

declare(strict_types=1);

namespace Concise\Teams\Http\Controllers;

use App\Actions\Fortify\CreateNewUser;
use Concise\Teams\Actions\AcceptInvitation;
use Concise\Teams\Models\TeamInvitation;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;

/**
 * Registration through an invitation: the invitee sets a name and password;
 * the email is the invitation's and can't be changed. Both pages are signed
 * (the signature travels in the form's action URL) so only someone holding
 * the emailed link can reach them, and the invitation itself authorizes the
 * sign-up — public registration may be off. The new account is marked
 * verified: following a signed link sent to that address is the same proof
 * Laravel's own verification link relies on.
 */
class RegisterFromInvitation
{
    public function show(Request $request, TeamInvitation $invitation): View|RedirectResponse
    {
        if ($request->user() !== null) {
            return redirect()->to($invitation->acceptUrl());
        }

        return view('teams::auth.register-from-invitation', [
            'invitation' => $invitation,
            'action' => URL::signedRoute('team.invitations.register.store', ['invitation' => $invitation]),
        ]);
    }

    public function store(Request $request, TeamInvitation $invitation, CreateNewUser $createUser, AcceptInvitation $accept): RedirectResponse
    {
        if ($request->user() !== null) {
            return redirect()->to($invitation->acceptUrl());
        }

        // CreateNewUser validates (names, password rules, email uniqueness) and hashes; the email is never the form's to set.
        $user = $createUser->create([
            ...$request->only(['first_name', 'last_name', 'password', 'password_confirmation']),
            'email' => $invitation->email,
        ]);

        $user->markEmailAsVerified();
        Auth::login($user);
        $request->session()->regenerate();

        $team = $accept($invitation, $user);

        Notification::make()
            ->title(team_trans('invitations.welcome', ['name' => $team->name]))
            ->success()
            ->send();

        return redirect()->route('team.dashboard', ['team' => $team->slug]);
    }
}
