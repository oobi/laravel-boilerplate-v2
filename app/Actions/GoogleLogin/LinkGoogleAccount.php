<?php

declare(strict_types=1);

namespace App\Actions\GoogleLogin;

use App\Enums\SocialProvider;
use App\Models\SocialAccount;
use App\Models\User;
use App\Notifications\Auth\GoogleAccountLinked;
use App\Support\Auth\GoogleLogin;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\AbstractUser as GoogleUser;

/**
 * Links a Google account to a user, at a first Google login or from the
 * profile, and tells the user by email. Only a verified Google address from an
 * allowed domain, never one already linked to someone, and one Google account
 * per user. Refusals are a ValidationException under `email`.
 */
class LinkGoogleAccount
{
    public function handle(User $user, GoogleUser $googleUser): SocialAccount
    {
        $email = GoogleLogin::verifiedEmail($googleUser);

        try {
            $account = DB::transaction(function () use ($user, $googleUser, $email): SocialAccount {
                if (GoogleLogin::findAccount($googleUser) !== null) {
                    GoogleLogin::refuse('auth.google_linked_elsewhere');
                }

                if ($user->googleAccount()->exists()) {
                    GoogleLogin::refuse('auth.google_already_linked');
                }

                return $user->googleAccount()->create([
                    'provider' => SocialProvider::GOOGLE,
                    'provider_user_id' => (string) $googleUser->getId(),
                    'email' => $email,
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            // Another request linked the same account (or this user) a moment ago.
            GoogleLogin::refuse('auth.google_linked_elsewhere');
        }

        $user->notify(new GoogleAccountLinked($email));

        return $account;
    }
}
