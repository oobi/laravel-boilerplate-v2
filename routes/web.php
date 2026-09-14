<?php

use App\Enums\SystemPermission;
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

// teams:start — when the teams tier's custom-domain overlay is in host mode the
// control plane (admin, auth, team picker) serves on admin_host only, while the
// public landing keeps the apex/base host; teams live on their own hosts (5h.4).
// config() is null-safe when the teams package is absent, so both are null in
// path mode and the groups below carry no host constraint — the default.
$hostMode = (bool) config('teams.domains.enabled', false);
$apexHost = $hostMode ? config('teams.domains.base') : null;
$adminHost = $hostMode ? config('teams.domains.admin_host') : null;
// In host mode the admin_host IS the admin area, so the `/admin` path segment is
// redundant (admin.example.com/dashboard, not /admin/dashboard); path mode keeps it.
$adminPath = $hostMode ? '' : 'admin';
// teams:end

// Public landing page — reachable by guests and authenticated users alike (see layouts.public).
Route::domain($apexHost)->group(function (): void {
    Route::get('/', Home::class)->name('home');
});

// The admin area requires an active system role (e.g. dashboard, users) — route names keep their existing flat prefixes regardless of the URL path.
Route::domain($adminHost)->prefix($adminPath)->group(function (): void {
    Route::middleware(['auth', 'verified', 'can:'.SystemPermission::ACCESS_ADMIN_PANEL->value])->group(function (): void {
        Route::get('/dashboard', Dashboard::class)->name('dashboard');

        // Self-service account management for admin-panel users — rendered in the admin shell, one route per tab.
        // A user without panel access has no account area here (403); a customer-facing frontend is out of scope
        // for this boilerplate (see docs/architecture.md). Each 2FA action is guarded by its own inline password prompt.
        Route::get('/profile', EditProfile::class)->name('profile.edit');
        Route::get('/profile/password', EditPassword::class)->name('profile.password');
        Route::get('/profile/two-factor', TwoFactorAuthentication::class)->name('profile.two-factor');

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

    // Leaving impersonation must stay reachable while masquerading as a user without a system role, so it sits outside the
    // ACCESS_ADMIN_PANEL gate above. Starting impersonation is still guarded by canImpersonate()/canBeImpersonated() inside
    // the package's own controller. Registers users.impersonate / users.impersonate.leave (lab404/laravel-impersonate).
    Route::middleware('auth')->prefix('users')->name('users.')->group(function (): void {
        Route::impersonate();
    });
});
