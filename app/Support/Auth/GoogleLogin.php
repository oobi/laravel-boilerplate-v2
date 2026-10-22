<?php

declare(strict_types=1);

namespace App\Support\Auth;

use App\Enums\SocialProvider;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\AbstractUser as GoogleUser;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;

/**
 * Log in with Google: whether it's switched on, the configured Socialite
 * driver, and the checks on the account Google sends back. Off until
 * GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET are set, so an app that doesn't
 * want it shows no button and its routes 404. Refusals are a
 * ValidationException under `email`.
 */
final class GoogleLogin
{
    /** The session key marking a link started from the profile, holding the user's id. */
    public const LINKING_KEY = 'google_login.linking_user_id';

    public static function enabled(): bool
    {
        return filled(config('services.google.client_id')) && filled(config('services.google.client_secret'));
    }

    /**
     * Workspace domains allowed to sign in; empty allows any Google account.
     *
     * @return list<string>
     */
    public static function allowedDomains(): array
    {
        return config('services.google.allowed_domains', []);
    }

    /**
     * The Google driver with PKCE on, returning to the account host's callback
     * unless GOOGLE_REDIRECT_URI overrides it. With one allowed domain Google is
     * asked to offer only that domain's accounts; it's a hint, checked again on
     * the way back.
     */
    public static function driver(): Provider
    {
        /** @var GoogleProvider $driver (or Socialite's fake in tests, which forwards to it) */
        $driver = Socialite::driver('google');

        $driver->redirectUrl(config('services.google.redirect') ?: route('google.callback'))->enablePKCE();

        $domains = self::allowedDomains();

        return count($domains) === 1 ? $driver->with(['hd' => $domains[0]]) : $driver;
    }

    /**
     * Removes the user's Google link, if any, returning whether there was one.
     * Called by the profile and the admin Security panel, and on every account
     * recovery (a reset link, an admin setting the password), so a link someone
     * else made doesn't outlive the reset (#61).
     */
    public static function unlink(User $user): bool
    {
        return $user->googleAccount()->delete() > 0;
    }

    /** The link for this Google account, matched on its stable id. */
    public static function findAccount(GoogleUser $googleUser): ?SocialAccount
    {
        return SocialAccount::query()
            ->where('provider', SocialProvider::GOOGLE)
            ->where('provider_user_id', (string) $googleUser->getId())
            ->first();
    }

    /**
     * The Google account's address, normalized, once Google vouches for it and
     * its Workspace domain (`hd`, set only for Workspace accounts) is allowed.
     */
    public static function verifiedEmail(GoogleUser $googleUser): string
    {
        $raw = $googleUser->getRaw();
        $email = User::normalizeEmail($googleUser->getEmail());

        if (! filled($email) || ! filter_var($raw['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            self::refuse('auth.google_unverified_email');
        }

        $domains = self::allowedDomains();

        if ($domains !== [] && ! in_array(strtolower((string) ($raw['hd'] ?? '')), $domains, true)) {
            self::refuse('auth.google_domain_not_allowed', ['domains' => implode(', ', $domains)]);
        }

        return $email;
    }

    /**
     * Whether Google runs the mailbox itself (Gmail, or a Workspace account),
     * so holding the Google account means holding the address. A Google
     * account made on any other address was checked against it once, at sign
     * up, and stays with whoever made it after they lose the mailbox: Google's
     * own guidance is not to match accounts on such an address.
     */
    public static function ownsAddress(GoogleUser $googleUser): bool
    {
        return filled($googleUser->getRaw()['hd'] ?? null)
            || Str::endsWith((string) User::normalizeEmail($googleUser->getEmail()), ['@gmail.com', '@googlemail.com']);
    }

    /** @param  array<string, string>  $replace */
    public static function refuse(string $key, array $replace = []): never
    {
        throw ValidationException::withMessages(['email' => [__($key, $replace)]]);
    }
}
