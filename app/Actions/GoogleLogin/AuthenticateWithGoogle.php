<?php

declare(strict_types=1);

namespace App\Actions\GoogleLogin;

use App\Models\User;
use App\Support\Auth\GoogleLogin;
use App\Support\Auth\LoginEligibility;
use Laravel\Socialite\AbstractUser as GoogleUser;

/**
 * The user a Google login signs in, after the same checks a password login
 * runs (LoginEligibility). Signing in is the caller's job, so the two-factor
 * challenge can come first. Refusals are a ValidationException under `email`.
 *
 * A Google account already linked signs in its user, whatever either address
 * is now. Otherwise it's linked, once, to the user with the same address, but
 * only when Google runs that mailbox (GoogleLogin::ownsAddress()) and the
 * address is verified here too: an unverified account may have been opened by
 * someone else in the owner's name, with a password they know. Anyone else
 * links Google from their profile, behind their password. Google never
 * creates an account.
 */
class AuthenticateWithGoogle
{
    public function __construct(private LinkGoogleAccount $linkGoogleAccount) {}

    public function handle(GoogleUser $googleUser): User
    {
        $email = GoogleLogin::verifiedEmail($googleUser);
        $account = GoogleLogin::findAccount($googleUser);

        // Before any lookup, so it doesn't tell whoever holds the Google account whether an account here uses the address.
        if ($account === null && ! GoogleLogin::ownsAddress($googleUser)) {
            GoogleLogin::refuse('auth.google_link_from_profile');
        }

        // A trashed user is out of reach of the relation, so reads as no account.
        $user = $account !== null ? $account->user : User::where('email', $email)->first();

        if ($user === null) {
            GoogleLogin::refuse('auth.google_no_account');
        }

        if ($account === null && ! $user->hasVerifiedEmail()) {
            GoogleLogin::refuse('auth.google_unverified_account');
        }

        if ($refusal = LoginEligibility::refusal($user)) {
            GoogleLogin::refuse($refusal);
        }

        if ($account === null) {
            $this->linkGoogleAccount->handle($user, $googleUser);
        } elseif ($account->email !== $email) {
            $account->update(['email' => $email]);
        }

        return $user;
    }
}
