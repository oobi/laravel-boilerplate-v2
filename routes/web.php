<?php

use App\Enums\SystemPermission;
use App\Http\Controllers\GoogleLoginController;
use App\Http\Controllers\ImpersonationController;
// public:start
// public:canonical: use App\Http\Controllers\LoginOnlyHomeController;
// public:end
use App\Http\Middleware\DenyLowAssuranceSessions;
use App\Livewire\Admin\Dashboard;
use App\Livewire\Admin\Roles\CreateRole;
use App\Livewire\Admin\Roles\ManageRoles;
use App\Livewire\Admin\Users\CreateUser;
use App\Livewire\Admin\Users\EditUser;
use App\Livewire\Admin\Users\ListUsers;
use App\Livewire\Admin\Users\ShowUser;
// public:start
use App\Livewire\Home;
// public:end
use App\Livewire\Profile\EditPassword;
use App\Livewire\Profile\EditProfile;
use App\Livewire\Profile\TwoFactorAuthentication;
use Illuminate\Support\Facades\Route;

// Path mode: no host constraint, admin under /admin. Defined outside the teams
// fence so the file still loads once bp:remove-teams strips it.
$apexHost = $accountHost = $adminHost = null;
$adminPath = 'admin';

// teams:start — when the teams tier's custom-domain overlay is in host mode,
// three hosts carry three different things: the apex is the public landing;
// account_host (default the apex, overridable for a headless-apex deployment)
// is the account layer everyone needs (auth, profile, team picker/onboarding,
// invitations — see routes/teams.php); admin_host is the admin area ONLY —
// nothing else may ever link to it (see docs/boilerplate/teams-domains.md).
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

// `/`, the `home` route: the public landing page, reachable by guests and signed-in users
// alike. A login-only app (bp:make-login-only) has none: `/` sends a guest to login and a
// signed-in user where they belong, signing out one nothing claims.
Route::domain($apexHost)->group(function (): void {
    // public:start
    // public:canonical: Route::get('/', LoginOnlyHomeController::class)->name('home');
    Route::get('/', Home::class)->name('home');
    // public:end
});

// Self-service account management — any signed-in, verified user, not just admin-panel
// staff (see docs/boilerplate/architecture.md). One route per tab; each 2FA action is guarded by its
// own inline password prompt. Deliberately NOT behind ACCESS_ADMIN_PANEL: a profile is not
// an admin page, and in host mode this group binds to account_host, never admin_host.
// Impersonated sessions are refused: only the account holder manages it. The profile tab
// itself skips `verified` so someone who mistyped their address at sign-up can fix it
// (changing it needs the current password and sends a new link); the rest need it.
Route::domain($accountHost)->middleware(['auth', DenyLowAssuranceSessions::class])->group(function (): void {
    Route::get('/profile', EditProfile::class)->name('profile.edit');
});

Route::domain($accountHost)->middleware(['auth', 'verified', DenyLowAssuranceSessions::class])->group(function (): void {
    Route::get('/profile/password', EditPassword::class)->name('profile.password');
    Route::get('/profile/two-factor', TwoFactorAuthentication::class)->name('profile.two-factor');
});

// Log in with Google (off, and these 404, until GOOGLE_CLIENT_ID/SECRET are set), on the
// account host beside Fortify's login. One callback serves a login and a link started
// from the profile's Authentication tab (behind its password prompt). See
// docs/boilerplate/authentication.md.
Route::domain($accountHost)->middleware('throttle:google-login')->group(function (): void {
    Route::get('/auth/google/redirect', [GoogleLoginController::class, 'redirect'])->middleware('guest')->name('google.redirect');
    Route::get('/auth/google/callback', [GoogleLoginController::class, 'callback'])->name('google.callback');
});

// The admin area requires an active system role (e.g. dashboard, users) — route names keep their existing flat prefixes regardless of the URL path.
Route::domain($adminHost)->prefix($adminPath)->group(function (): void {
    Route::middleware(['auth', 'verified', 'can:'.SystemPermission::ACCESS_ADMIN_PANEL->value])->group(function (): void {
        // The admin root (host mode: admin_host/; path mode: /admin) sends admins to
        // the dashboard rather than 404ing. Gated like the rest, so a guest bounces
        // to login (on the account host) — the admin host never shows a form.
        Route::get('/', fn () => redirect()->route('dashboard'));
        Route::get('/dashboard', Dashboard::class)->name('dashboard');

        // Each area's read floor is on its route, not only in the page's mount():
        // Livewire re-runs `can:` middleware on every update request, so access
        // lost mid-session is refused at the next click (docs/boilerplate/permissions.md).
        Route::prefix('users')->name('users.')->middleware('can:'.SystemPermission::VIEW_USERS->value)->group(function (): void {
            Route::get('/', ListUsers::class)->name('index');
            Route::get('/create', CreateUser::class)->name('create');
            Route::get('/{user}', ShowUser::class)->name('show');
            Route::get('/{user}/edit', EditUser::class)->name('edit');
        });

        Route::prefix('roles')->name('roles.')->middleware('can:'.SystemPermission::MANAGE_ROLES->value)->group(function (): void {
            Route::get('/', ManageRoles::class)->name('index');
            Route::get('/create', CreateRole::class)->name('create');
            Route::get('/{role}/edit', ManageRoles::class)->name('edit');
        });
    });
});

// Starting impersonation is NOT a route: it runs inside the CSRF-protected
// Livewire/Filament action that offers it (App\Actions\Impersonation\StartImpersonation),
// so no plain navigation can trigger it (GitHub #11). Leaving is a POST — never
// GET — driven by the banner's CSRF-protected "stop impersonating" form. It lives
// on the ACCOUNT host, never the (fenceable) admin host, so an impersonator can
// always exit even off the admin network (see docs/boilerplate/teams-domains.md). Behind
// `auth` only: the masqueraded user may be a non-admin. $accountHost is null in
// path mode (unconstrained).
Route::domain($accountHost)->middleware('auth')->prefix('users')->name('users.')->group(function (): void {
    Route::post('impersonate/leave', [ImpersonationController::class, 'leave'])->name('impersonate.leave');
});
