<?php

declare(strict_types=1);

use Concise\Teams\Http\Controllers\TeamRedirect;
use Concise\Teams\Http\Middleware\ResolveTeamContext;
use Concise\Teams\Livewire\Team\Dashboard;
use Concise\Teams\Livewire\Team\Onboarding;
use Illuminate\Support\Facades\Route;

$prefix = config('teams.route_prefix', 'teams');

Route::middleware(['web', 'auth', 'verified'])
    ->prefix($prefix)
    ->name('team.')
    ->group(function () {
        // Entry point + zero-team onboarding (no {team}, so no team context).
        Route::get('/', TeamRedirect::class)->name('index');
        Route::get('/onboarding', Onboarding::class)->name('onboarding');

        // Team-scoped pages: {team} slug is resolved + authorized by the middleware.
        Route::middleware(ResolveTeamContext::class)
            ->prefix('{team}')
            ->group(function () {
                Route::get('/dashboard', Dashboard::class)->name('dashboard');
            });
    });
