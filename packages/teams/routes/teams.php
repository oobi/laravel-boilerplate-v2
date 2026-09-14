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
use Concise\Teams\Livewire\Team\SelectTeam;
use Concise\Teams\Livewire\Team\Settings;
use Concise\Teams\Support\DomainPolicy;
use Illuminate\Support\Facades\Route;

$prefix = config('teams.route_prefix', 'teams');

// A team host is a full dotted hostname. A domain parameter otherwise defaults to
// a single dotless label, which would never match a host like `acme.com`.
Route::pattern('teamHost', '[A-Za-z0-9.\-]+');

// The team-scoped pages (members-only, one team's context at a time — scope §6/§7).
// The {team}/{teamHost} parameter is resolved + authorized by ResolveTeamContext.
$teamPages = function (): void {
    Route::get('/dashboard', Dashboard::class)->name('dashboard');
    Route::get('/members', ListMembers::class)->name('members');
    Route::get('/invitations', ListInvitations::class)->name('invitations');
    Route::get('/settings', Settings::class)->name('settings');
};

// Entry point + zero-team onboarding: no team context, always on the app host
// (path prefix). In host mode these move to the control-plane host in 5h.4b.
Route::middleware(['web', 'auth', 'verified'])
    ->prefix($prefix)
    ->name('team.')
    ->group(function () {
        Route::get('/', TeamRedirect::class)->name('index');
        Route::get('/onboarding', Onboarding::class)->name('onboarding');
        Route::get('/select', SelectTeam::class)->name('select');
    });

if (DomainPolicy::enabled()) {
    // Host mode: the team is implied by the request host — same route names, no
    // {team} path segment (~dev/TEAMS_DOMAINS_SCOPE.md §5). {teamHost} matches a
    // full dotted host via the Route::pattern above.
    Route::middleware(['web', 'auth', 'verified', ResolveTeamContext::class])
        ->domain('{teamHost}')
        ->name('team.')
        ->group($teamPages);
} else {
    // Path mode (default): team pages under /{prefix}/{team}.
    Route::middleware(['web', 'auth', 'verified', ResolveTeamContext::class])
        ->prefix($prefix.'/{team}')
        ->name('team.')
        ->group($teamPages);
}

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

// The system admin area: "all teams", in the admin shell, gated by the `view
// teams` system permission at each page (never by membership); edits need
// `manage teams` and the finer team permissions. Flat `teams.*` route names so
// Breadcrumbs derives the parent crumb.
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
