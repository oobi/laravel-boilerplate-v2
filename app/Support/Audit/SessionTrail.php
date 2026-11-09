<?php

declare(strict_types=1);

namespace App\Support\Audit;

use Illuminate\Support\Str;

/**
 * Links the entries made in one browser session, so they can be read back in the
 * order they happened. A random ULID kept in the session, never Laravel's session
 * ID: that is a credential anyone reading the log could use, and it changes at
 * login. Session data survives that change, and the logout entry is written
 * before the session is cleared, so one trail runs from the first audited event
 * (often failed logins) to the logout.
 */
final class SessionTrail
{
    public const string SESSION_KEY = 'audit.session_trail';

    /** Hidden Context key, so a job dispatched in the session joins its trail. */
    public const string CONTEXT = 'audit.session_trail';

    /** The current web request's trail, started on first use. Null without a route or a session. */
    public static function forRequest(): ?string
    {
        if (! app()->bound('request') || request()->route() === null || ! request()->hasSession()) {
            return null;
        }

        $session = request()->session();

        if (! $session->isStarted()) {
            return null;
        }

        $trail = self::normalize($session->get(self::SESSION_KEY));

        if ($trail === null) {
            $trail = (string) Str::ulid();
            $session->put(self::SESSION_KEY, $trail);
        }

        return $trail;
    }

    /** A trail as stored, from whatever a URL, form or session holds; null when it isn't one. */
    public static function normalize(mixed $value): ?string
    {
        return is_string($value) && Str::isUlid($value) ? strtoupper($value) : null;
    }
}
