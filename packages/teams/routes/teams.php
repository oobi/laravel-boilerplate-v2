<?php

declare(strict_types=1);

use App\Enums\SystemPermission;
use Concise\Teams\Http\Controllers\AcceptTeamInvitation;
use Concise\Teams\Http\Controllers\RegisterFromInvitation;
use Concise\Teams\Http\Controllers\TeamRedirect;
use Concise\Teams\Http\Middleware\ResolveTeamContext;
use Concise\Teams\Livewire\Admin\Teams\ListTeams;
use Concise\Teams\Livewire\Admin\Teams\ShowTeam;
use Concise\Teams\Livewire\Admin\Teams\TeamInvitations;
use Concise\Teams\Livewire\Admin\Teams\TeamMembers;
use Concise\Teams\Livewire\Admin\Teams\TeamSettings;
use Concise\Teams\Livewire\Team\Dashboard;
use Concise\Teams\Livewire\Team\ListInvitations;
use Concise\Teams\Livewire\Team\ListMembers;
use Concise\Teams\Livewire\Team\Onboarding;
use Illuminate\Support\Facades\Route;

$prefix = config('teams.route_prefix', 'teams');

// The team area: members-only, one team's context at a time (scope §6/§7).
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
                Route::get('/members', ListMembers::class)->name('members');
                Route::get('/invitations', ListInvitations::class)->name('invitations');
            });
    });

// Invitation links: signed, and deliberately NOT behind auth. The signature is
// what authorizes them (it's the emailed link); AcceptTeamInvitation then routes
// a guest to sign in or to register through the invitation, and holds a
// signed-in account to the invited email.
Route::middleware(['web', 'signed'])
    ->prefix($prefix.'/invitations/{invitation}')
    ->name('team.invitations.')
    ->group(function () {
        Route::get('/accept', AcceptTeamInvitation::class)->name('accept');
        Route::get('/register', [RegisterFromInvitation::class, 'show'])->name('register');
        Route::post('/register', [RegisterFromInvitation::class, 'store'])->name('register.store');
    });

// The system admin area: "manage all teams", in the admin shell, gated by the
// `manage teams` system permission (never by membership). Flat `teams.*` route
// names so Breadcrumbs derives the parent crumb.
Route::middleware(['web', 'auth', 'verified', 'can:'.SystemPermission::ACCESS_ADMIN_PANEL->value])
    ->prefix('admin/teams')
    ->name('teams.')
    ->group(function () {
        Route::get('/', ListTeams::class)->name('index');
        // One route per tab (like the profile screens): Overview / Members / Invitations / Settings.
        Route::get('/{team}', ShowTeam::class)->name('show');
        Route::get('/{team}/members', TeamMembers::class)->name('members');
        Route::get('/{team}/invitations', TeamInvitations::class)->name('invitations');
        Route::get('/{team}/settings', TeamSettings::class)->name('settings');
    });
