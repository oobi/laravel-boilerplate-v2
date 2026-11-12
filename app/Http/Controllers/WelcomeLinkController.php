<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Auth\WelcomeLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

/**
 * Opens a new user's welcome link: mints a password reset token and hands on
 * to Fortify's reset page, where they choose a password. A link that is
 * tampered with, expired, already used (a password has been set or the address
 * changed since) or for an account that can't sign in lands on forgot-password
 * with a notice, never a bare 403, so they can ask for a fresh link. Guest only,
 * like Fortify's reset pages: a signed-in visitor goes home, nothing minted.
 */
class WelcomeLinkController extends Controller
{
    public function __invoke(Request $request, int $user, string $hash): RedirectResponse
    {
        $account = $request->hasValidSignature() ? User::query()->find($user) : null;

        if ($account === null || ! $account->active || ! WelcomeLink::isCurrent($account, $hash)) {
            return redirect()->route('password.request')->with('link_invalid', __('auth.welcome_link_invalid'));
        }

        return redirect()->route('password.reset', [
            'token' => Password::broker()->createToken($account),
            'email' => $account->getEmailForPasswordReset(),
        ]);
    }
}
