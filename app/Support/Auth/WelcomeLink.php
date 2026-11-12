<?php

declare(strict_types=1);

namespace App\Support\Auth;

use App\Models\User;
use Carbon\CarbonInterval;

/**
 * The emailed link that lets a user an admin created choose their own
 * password (WelcomeNewUser). It lasts longer than a reset link
 * (auth.welcome.expire), so it carries no reset token: opening it mints a
 * fresh one and hands on to Fortify's reset page (WelcomeLinkController).
 *
 * The link is signed and holds a fingerprint of the account's email address
 * and password hash, so it dies as soon as any password is set (by them or by
 * an admin) or the address changes: an admin correcting a mistyped address
 * mustn't leave a working link at the wrong one. Built from APP_URL like every
 * mailed link (MailedLinks). The fingerprint uses the current APP_KEY only, so
 * rotating the key also ends outstanding links.
 */
final class WelcomeLink
{
    public static function url(User $user): string
    {
        return MailedLinks::generator()->temporarySignedRoute(
            'password.welcome',
            now()->addMinutes(config()->integer('auth.welcome.expire')),
            ['user' => $user->getKey(), 'hash' => self::fingerprint($user)],
        );
    }

    /** Whether a link's fingerprint still matches: no password set and no address change since it was sent. */
    public static function isCurrent(User $user, string $hash): bool
    {
        return hash_equals(self::fingerprint($user), $hash);
    }

    /** The link's lifetime in words, e.g. "1 week", for the email and the form. */
    public static function lifetime(): string
    {
        return CarbonInterval::minutes(config()->integer('auth.welcome.expire'))->cascade()->forHumans();
    }

    private static function fingerprint(User $user): string
    {
        return hash_hmac('sha256', $user->getEmailForPasswordReset().'|'.$user->getAuthPassword(), (string) config('app.key'));
    }
}
