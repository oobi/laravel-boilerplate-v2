<?php

use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsureRememberedUserIsNotLockedOut;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Session\Middleware\AuthenticateSession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Theme preference is a plain (unencrypted) cookie so it can be read
        // by the blocking inline script in <head> before Alpine/Livewire boot.
        $middleware->encryptCookies(except: ['theme']);

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
        $middleware->web(append: [
            AuthenticateSession::class,
            EnsureAccountIsActive::class,
            EnsureRememberedUserIsNotLockedOut::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
