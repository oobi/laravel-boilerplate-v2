<?php

use App\Livewire\Admin\CreateUser;
use App\Livewire\Admin\EditUser;
use App\Livewire\Admin\ListUsers;
use App\Livewire\Admin\ShowUser;
use App\Livewire\Dashboard;
use App\Livewire\EditProfile;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect(auth()->check() ? '/dashboard' : '/login');
});

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('/dashboard', Dashboard::class)->name('dashboard');

    Route::get('/profile', EditProfile::class)->name('profile.edit');

    Route::prefix('users')->name('users.')->group(function (): void {
        Route::get('/', ListUsers::class)->name('index');
        Route::get('/create', CreateUser::class)->name('create');
        Route::get('/{user}', ShowUser::class)->name('show');
        Route::get('/{user}/edit', EditUser::class)->name('edit');
    });
});
