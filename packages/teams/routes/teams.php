<?php

declare(strict_types=1);

use App\Enums\SystemPermission;
use Concise\Teams\Http\Controllers\AcceptTeamInvitation;
use Concise\Teams\Http\Controllers\RegisterFromInvitation;
use Concise\Teams\Http\Controllers\TeamRedirect;
use Concise\Teams\Http\Middleware\ResolveTeamContext;
use Concise\Teams\Livewire\Admin\Teams\ListTeams;
use Concise\Teams\Livewire\Admin\Teams\ShowTeam;
use Concise\Teams\Livewire\Admin\Teams\TeamDomains;
use Concise\Teams\Livewire\Admin\Teams\TeamInvitations;
use Concise\Teams\Livewire\Admin\Teams\TeamMembers;
use Concise\Teams\Livewire\Admin\Teams\TeamSettings;
use Concise\Teams\Livewire\Team\Dashboard;
use Concise\Teams\Livewire\Team\Domains;
use Concise\Teams\Livewire\Team\ListInvitations;
use Concise\Teams\Livewire\Team\ListMembers;
use Concise\Teams\Livewire\Team\Onboarding;
use Concise\Teams\Livewire\Team\SelectTeam;
use Concise\Teams\Livewire\Team\Settings;
use Concise\Teams\Support\DomainPolicy;
use Illuminate\Support\Facades\Route;

$prefix = config('teams.route_prefix', 'teams');

$hostMode = DomainPolicy::enabled();
$accountHost = DomainPolicy::accountHost();
// adminHost() itself is a raw, ungated accessor (also used for reservation
// checks regardless of mode — see CreateDomain::isReserved); gate it here so
// path mode never picks up a stray admin_host env value as a route constraint.
$adminHost = $hostMode ? DomainPolicy::adminHost() : null;

// The entry pages and invitation links keep their `{prefix}` segment in BOTH
// modes now (`/teams`, `/teams/select`, `/teams/invitations/…`) — in host mode
// they bind to account_host, which is a real page-serving host (default the
// apex), not a bare control-plane root, so the prefix still reads. Only the
// system "all teams" admin area drops its redundant `admin/` segment in host
// mode, since admin_host IS the admin area (admin_host/teams, not
// admin_host/admin/teams).
$entryPrefix = $prefix;
$invitationPrefix = $prefix.'/invitations/{invitation}';
$adminTeamsPrefix = $hostMode ? 'teams' : 'admin/teams';

// A team host is a full dotted hostname, excluding the admin host, the account
// host, and the apex (a domain parameter otherwise defaults to a single
// dotless label; an unexcluded wildcard would shadow those routes — see
// DomainPolicy).
Route::pattern('teamHost', DomainPolicy::teamHostPattern());

// The team-scoped pages (members-only, one team's context at a time — scope §6/§7).
// The {team}/{teamHost} parameter is resolved + authorized by ResolveTeamContext.
$teamPages = function (): void {
    Route::get('/dashboard', Dashboard::class)->name('dashboard');
    Route::get('/members', ListMembers::class)->name('members');
    Route::get('/invitations', ListInvitations::class)->name('invitations');
    // Custom domains, when that tier is on (the page 404s otherwise).
    Route::get('/domains', Domains::class)->name('domains');
    Route::get('/settings', Settings::class)->name('settings');
};

// Entry point + zero-team onboarding: no team context. On the account host in
// host mode, the app host otherwise. team.index is the front door — behind
// auth, so a guest is sent to login and a member on to their team.
Route::middleware(['web', 'auth', 'verified'])
    ->domain($accountHost)
    ->prefix($entryPrefix)
    ->name('team.')
    ->group(function () {
        Route::get('/', TeamRedirect::class)->name('index');
        Route::get('/onboarding', Onboarding::class)->name('onboarding');
        Route::get('/select', SelectTeam::class)->name('select');
    });

if ($hostMode) {
    // Host mode: the team is implied by the request host — same route names, no
    // {team} path segment (see docs/teams-domains.md). {teamHost} matches a
    // full dotted host via the Route::pattern above.
    Route::middleware(['web', 'auth', 'verified', ResolveTeamContext::class])
        ->domain('{teamHost}')
        ->name('team.')
        ->group(function () use ($teamPages) {
            // The team-host root is the team's dashboard (team1.bp.test/ → its dashboard).
            Route::get('/', fn () => redirect('/dashboard'));
            $teamPages();
        });
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
    ->domain($accountHost)
    ->prefix($invitationPrefix)
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
    ->domain($adminHost)
    ->prefix($adminTeamsPrefix)
    ->name('teams.')
    ->group(function () {
        Route::get('/', ListTeams::class)->name('index');
        // One route per tab (like the profile screens): Overview / Members / Invitations / Settings.
        Route::get('/{team}', ShowTeam::class)->name('show');
        Route::get('/{team}/members', TeamMembers::class)->name('members');
        Route::get('/{team}/invitations', TeamInvitations::class)->name('invitations');
        // Custom domains, when that tier is on (the page 404s otherwise).
        Route::get('/{team}/domains', TeamDomains::class)->name('domains');
        Route::get('/{team}/settings', TeamSettings::class)->name('settings');
    });
