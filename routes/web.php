<?php

use App\Enums\SystemPermission;
use App\Http\Controllers\ImpersonationController;
use App\Livewire\Admin\Dashboard;
use App\Livewire\Admin\Roles\CreateRole;
use App\Livewire\Admin\Roles\ManageRoles;
use App\Livewire\Admin\Users\CreateUser;
use App\Livewire\Admin\Users\EditUser;
use App\Livewire\Admin\Users\ListUsers;
use App\Livewire\Admin\Users\ShowUser;
use App\Livewire\Home;
use App\Livewire\Profile\EditPassword;
use App\Livewire\Profile\EditProfile;
use App\Livewire\Profile\TwoFactorAuthentication;
use Illuminate\Support\Facades\Route;

// teams:start — when the teams tier's custom-domain overlay is in host mode,
// three hosts carry three different things: the apex is the public landing;
// account_host (default the apex, overridable for a headless-apex deployment)
// is the account layer everyone needs (auth, profile, team picker/onboarding,
// invitations — see routes/teams.php); admin_host is the admin area ONLY —
// nothing else may ever link to it (see ~dev/TEAMS_DOMAINS_HOST_SPLIT.md).
// config() is null-safe when the teams package is absent, so every host is
// null in path mode and the groups below carry no host constraint — the default.
$hostMode = (bool) config('teams.domains.enabled', false);
$apexHost = $hostMode ? config('teams.domains.base') : null;
$accountHost = $hostMode ? (config('teams.domains.account_host') ?: config('teams.domains.base')) : null;
$adminHost = $hostMode ? config('teams.domains.admin_host') : null;
// In host mode the admin_host IS the admin area, so the `/admin` path segment is
// redundant (admin.example.com/dashboard, not /admin/dashboard); path mode keeps it.
$adminPath = $hostMode ? '' : 'admin';
// teams:end

// Public landing page — reachable by guests and authenticated users alike (see layouts.public).
Route::domain($apexHost)->group(function (): void {
    Route::get('/', Home::class)->name('home');
});

// Self-service account management — any signed-in, verified user, not just admin-panel
// staff (see docs/architecture.md). One route per tab; each 2FA action is guarded by its
// own inline password prompt. Deliberately NOT behind ACCESS_ADMIN_PANEL: a profile is not
// an admin page, and in host mode this group binds to account_host, never admin_host.
Route::domain($accountHost)->middleware(['auth', 'verified'])->group(function (): void {
    Route::get('/profile', EditProfile::class)->name('profile.edit');
    Route::get('/profile/password', EditPassword::class)->name('profile.password');
    Route::get('/profile/two-factor', TwoFactorAuthentication::class)->name('profile.two-factor');
});

// The admin area requires an active system role (e.g. dashboard, users) — route names keep their existing flat prefixes regardless of the URL path.
Route::domain($adminHost)->prefix($adminPath)->group(function (): void {
    Route::middleware(['auth', 'verified', 'can:'.SystemPermission::ACCESS_ADMIN_PANEL->value])->group(function (): void {
        // The admin root (host mode: admin_host/; path mode: /admin) sends admins to
        // the dashboard rather than 404ing. Gated like the rest, so a guest bounces
        // to login (on the account host) — the admin host never shows a form.
        Route::get('/', fn () => redirect()->route('dashboard'));
        Route::get('/dashboard', Dashboard::class)->name('dashboard');

        Route::prefix('users')->name('users.')->group(function (): void {
            Route::get('/', ListUsers::class)->name('index');
            Route::get('/create', CreateUser::class)->name('create');
            Route::get('/{user}', ShowUser::class)->name('show');
            Route::get('/{user}/edit', EditUser::class)->name('edit');
        });

        Route::prefix('roles')->name('roles.')->group(function (): void {
            Route::get('/', ManageRoles::class)->name('index');
            Route::get('/create', CreateRole::class)->name('create');
            Route::get('/{role}/edit', ManageRoles::class)->name('edit');
        });
    });

    // Start impersonation: staff only (ImpersonationController enforces
    // canImpersonate/canBeImpersonated), `auth` — not ACCESS_ADMIN_PANEL — so it
    // composes with that guard; the link only ever appears on admin pages. Lands
    // the impersonator on the target's own post-login destination.
    Route::middleware('auth')->prefix('users')->name('users.')->group(function (): void {
        Route::get('impersonate/take/{id}/{guardName?}', [ImpersonationController::class, 'take'])->name('impersonate');
    });
});

// Leaving impersonation lives on the ACCOUNT host, never the (fenceable) admin
// host, so an impersonator can always exit — even off the admin network
// (~dev/TEAMS_DOMAINS_HOST_SPLIT.md §5). Behind `auth` only: the masqueraded
// user may be a non-admin. $accountHost is null in path mode (unconstrained).
Route::domain($accountHost)->middleware('auth')->prefix('users')->name('users.')->group(function (): void {
    Route::get('impersonate/leave', [ImpersonationController::class, 'leave'])->name('impersonate.leave');
});
