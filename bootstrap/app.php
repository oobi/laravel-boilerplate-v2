<?php

use App\Http\Middleware\EnsureAccountIsActive;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

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

        // Enforce active-account status on every web request — covers
        // authenticated sessions AND pending 2FA challenges.
        $middleware->web(append: [
            EnsureAccountIsActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
