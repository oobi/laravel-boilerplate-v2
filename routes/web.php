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

// Public landing page — reachable by guests and authenticated users alike (see layouts.public).
Route::get('/', Home::class)->name('home');

// Everything under /admin requires an active system role (e.g. /admin/dashboard, /admin/users) — route names keep their existing flat prefixes.
Route::prefix('admin')->group(function (): void {
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
