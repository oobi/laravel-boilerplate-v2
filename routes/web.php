<?php

use App\Enums\SystemPermission;
use App\Livewire\Admin\Dashboard;
use App\Livewire\Admin\Roles\CreateRole;
use App\Livewire\Admin\Roles\EditRole;
use App\Livewire\Admin\Roles\ListRoles;
use App\Livewire\Admin\Users\CreateUser;
use App\Livewire\Admin\Users\EditUser;
use App\Livewire\Admin\Users\ListUsers;
use App\Livewire\Admin\Users\ShowUser;
use App\Livewire\EditProfile;
use App\Livewire\Home;
use App\Livewire\TwoFactorAuthentication;
use Illuminate\Support\Facades\Route;

// Public landing page — reachable by guests and authenticated users alike (see layouts.public).
Route::get('/', Home::class)->name('home');

// Any authenticated user can manage their own profile/security, regardless of system role.
Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('/profile', EditProfile::class)->name('profile.edit');

    // Re-confirming the password guards access to enabling/disabling 2FA and viewing recovery codes.
    Route::get('/profile/two-factor-authentication', TwoFactorAuthentication::class)
        ->middleware('password.confirm')
        ->name('two-factor.show');
});

// Everything under /admin requires an active system role (e.g. /admin/dashboard, /admin/users) — route names keep their existing flat prefixes.
Route::prefix('admin')->group(function (): void {
    Route::middleware(['auth', 'verified', 'can:'.SystemPermission::ACCESS_ADMIN_PANEL->value])->group(function (): void {
        Route::get('/dashboard', Dashboard::class)->name('dashboard');

        Route::prefix('users')->name('users.')->group(function (): void {
            Route::get('/', ListUsers::class)->name('index');
            Route::get('/create', CreateUser::class)->name('create');
            Route::get('/{user}', ShowUser::class)->name('show');
            Route::get('/{user}/edit', EditUser::class)->name('edit');
        });

        Route::prefix('roles')->name('roles.')->group(function (): void {
            Route::get('/', ListRoles::class)->name('index');
            Route::get('/create', CreateRole::class)->name('create');
            Route::get('/{role}/edit', EditRole::class)->name('edit');
        });
    });

    // Leaving impersonation must stay reachable while masquerading as a user without a system role, so it sits outside the
    // ACCESS_ADMIN_PANEL gate above. Starting impersonation is still guarded by canImpersonate()/canBeImpersonated() inside
    // the package's own controller. Registers users.impersonate / users.impersonate.leave (lab404/laravel-impersonate).
    Route::middleware('auth')->prefix('users')->name('users.')->group(function (): void {
        Route::impersonate();
    });
});
