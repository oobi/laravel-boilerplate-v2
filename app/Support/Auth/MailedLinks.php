<?php

declare(strict_types=1);

namespace App\Support\Auth;

use Illuminate\Http\Request;
use Illuminate\Routing\UrlGenerator;

/**
 * Links that go out by email are built from APP_URL, never from the request
 * that triggered the email. The request's Host (or a trusted proxy's
 * X-Forwarded-Host) is whatever the sender chose, so a reset link built from
 * it can hand a real token to another site (GitHub #58).
 *
 * generator() is a copy of the app's URL generator that acts as if the
 * request were for APP_URL; the shared one is untouched, so the teams tier's
 * subdomains and custom domains still work everywhere else. A route bound to
 * a domain (the account host in host mode) keeps that domain.
 */
final class MailedLinks
{
    public static function generator(): UrlGenerator
    {
        $appUrl = (string) config('app.url');

        $generator = clone app('url');
        $generator->setRequest(Request::create($appUrl));
        $generator->forceRootUrl($appUrl);

        return $generator;
    }
}
