<?php

declare(strict_types=1);

use Concise\ThemeDemo\Livewire\ComponentGallery;
use Concise\ThemeDemo\Livewire\Forms\DaisyForm;
use Concise\ThemeDemo\Livewire\Overview;
use Concise\ThemeDemo\Livewire\Tables\EmptyTable;
use Concise\ThemeDemo\Livewire\Tables\MaximalistTable;
use Concise\ThemeDemo\Livewire\Tables\SimpleTable;
use Illuminate\Support\Facades\Route;

// Not part of routes/web.php, so the 'web' middleware group isn't applied automatically.
Route::middleware(['web', 'auth', 'verified'])
    ->prefix('style-demo')
    ->name('style-demo.')
    ->group(function (): void {
        Route::get('/', Overview::class)->name('index');
        Route::get('/tables/empty', EmptyTable::class)->name('tables-empty');
        Route::get('/tables/simple', SimpleTable::class)->name('tables-simple');
        Route::get('/tables/maximalist', MaximalistTable::class)->name('tables-maximalist');
        Route::get('/forms/daisy', DaisyForm::class)->name('forms-daisy');
        Route::get('/components', ComponentGallery::class)->name('components');
    });
