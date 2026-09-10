<?php

declare(strict_types=1);

use App\Enums\SystemPermission;
use Concise\Teams\Http\Controllers\AcceptTeamInvitation;
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

        // The signed accept link from an invitation email. The signature proves the link is ours;
        // the signed-in account must be the invitee (AcceptInvitation). Guests log in first and return.
        Route::get('/invitations/{invitation}/accept', AcceptTeamInvitation::class)
            ->middleware('signed')
            ->name('invitations.accept');

        // Team-scoped pages: {team} slug is resolved + authorized by the middleware.
        Route::middleware(ResolveTeamContext::class)
            ->prefix('{team}')
            ->group(function () {
                Route::get('/dashboard', Dashboard::class)->name('dashboard');
                Route::get('/members', ListMembers::class)->name('members');
                Route::get('/invitations', ListInvitations::class)->name('invitations');
            });
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
