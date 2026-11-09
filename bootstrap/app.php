<?php

use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsureRememberedUserIsNotLockedOut;
use App\Http\Middleware\WarnAboutUntrustedProxy;
use App\Support\Auth\Destination;
use App\Support\Auth\ExpiredForms;
use App\Support\TrustedHosts;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\AuthenticateSession;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Answer only to APP_URL (and its subdomains) plus the hosts in
        // App\Support\TrustedHosts; any other Host gets a 400. Skipped locally.
        $middleware->trustHosts(at: fn (): array => TrustedHosts::patterns());

        // Theme preference is a plain (unencrypted) cookie so it can be read
        // by the blocking inline script in <head> before Alpine/Livewire boot.
        $middleware->encryptCookies(except: ['theme']);

        // A signed-in user opening a guest page (login, register, reset) goes
        // where they belong, not to the admin dashboard (which 403s a non-admin).
        // One nothing claims (a remember-me login under LOGIN_FALLBACK=reject)
        // lands on `home`: the public page, or in a login-only app
        // (bp:make-login-only) LoginOnlyHomeController, which signs them out.
        $middleware->redirectUsersTo(fn (Request $request): string => Destination::home($request->user()));

        // AuthenticateSession stamps the user's password hash into the session
        // and logs the session out on its next request once that hash changes.
        // This is what makes a password change (self-service, admin reset, or
        // reset-link) invalidate the user's *other* live sessions — the current
        // device is kept alive by Auth::logoutOtherDevices() at the change site.
        //
        // EnsureAccountIsActive enforces active-account status on every web
        // request — covers authenticated sessions AND pending 2FA challenges.
        //
        // EnsureRememberedUserIsNotLockedOut refuses a "remember me" login for
        // a user locked out by the mandatory-2FA grace period (password logins
        // are refused in AuthenticateUser). It only acts on the request where
        // the cookie logged the user in.
        //
        // WarnAboutUntrustedProxy logs (hourly at most) when forwarded headers
        // arrive from a proxy that isn't trusted: a missing TRUSTED_PROXIES.
        $middleware->web(append: [
            AuthenticateSession::class,
            EnsureAccountIsActive::class,
            EnsureRememberedUserIsNotLockedOut::class,
            WarnAboutUntrustedProxy::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // A sign-in form left open past the session lifetime goes back to the form
        // with a notice instead of the 419 page. Other forms keep the 419 page.
        $exceptions->render(fn (HttpException $e, Request $request) => ExpiredForms::recover($e, $request));
    })->create();
