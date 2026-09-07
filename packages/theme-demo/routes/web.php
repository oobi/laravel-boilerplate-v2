<?php

declare(strict_types=1);

use Concise\ThemeDemo\Livewire\ComponentGallery;
use Concise\ThemeDemo\Livewire\FilamentComponentGallery;
use Concise\ThemeDemo\Livewire\FilamentModalGallery;
use Concise\ThemeDemo\Livewire\Forms\DaisyForm;
use Concise\ThemeDemo\Livewire\Forms\FilamentForm;
use Concise\ThemeDemo\Livewire\ModalGallery;
use Concise\ThemeDemo\Livewire\Overview;
use Concise\ThemeDemo\Livewire\TabContent\TabContent;
use Concise\ThemeDemo\Livewire\Tables\EmptyTable;
use Concise\ThemeDemo\Livewire\Tables\FilamentTable;
use Concise\ThemeDemo\Livewire\Tables\MaximalistTable;
use Concise\ThemeDemo\Livewire\Tables\SimpleTable;
use Concise\ThemeDemo\Livewire\Tables\WideTable;
use Illuminate\Support\Facades\Route;

// Not part of routes/web.php, so the 'web' middleware group isn't applied automatically.
Route::middleware(['web', 'auth', 'verified'])
    ->prefix('admin/style-demo')
    ->name('style-demo.')
    ->group(function (): void {
        Route::get('/', Overview::class)->name('index');
        Route::get('/tables/empty', EmptyTable::class)->name('tables-empty');
        Route::get('/tables/simple', SimpleTable::class)->name('tables-simple');
        Route::get('/tables/maximalist', MaximalistTable::class)->name('tables-maximalist');
        Route::get('/tables/wide', WideTable::class)->name('tables-wide');
        Route::get('/tables/filament/empty', FilamentTable::class)->name('tables-filament-empty')->defaults('variant', 'empty');
        Route::get('/tables/filament/simple', FilamentTable::class)->name('tables-filament-simple')->defaults('variant', 'simple');
        Route::get('/tables/filament/maximalist', FilamentTable::class)->name('tables-filament-maximalist')->defaults('variant', 'maximalist');
        Route::get('/tables/filament/custom-header', FilamentTable::class)->name('tables-filament-custom-header')->defaults('variant', 'custom-header');
        Route::get('/tables/filament/wide', FilamentTable::class)->name('tables-filament-wide')->defaults('variant', 'wide');
        Route::get('/forms/daisy', DaisyForm::class)->name('forms-daisy')->defaults('variant', 'floating');
        Route::get('/forms/daisy/standard', DaisyForm::class)->name('forms-daisy-standard')->defaults('variant', 'standard');
        Route::get('/forms/filament', FilamentForm::class)->name('forms-filament');
        Route::get('/components', ComponentGallery::class)->name('components');
        Route::get('/components/filament', FilamentComponentGallery::class)->name('components-filament');
        Route::get('/modals/daisy', ModalGallery::class)->name('modals-daisy');
        Route::get('/modals/filament', FilamentModalGallery::class)->name('modals-filament');
        Route::get('/tab-content/table', TabContent::class)->name('tab-content-table')->defaults('variant', 'table');
        Route::get('/tab-content/form', TabContent::class)->name('tab-content-form')->defaults('variant', 'form');
        Route::get('/tab-content/panels', TabContent::class)->name('tab-content-panels')->defaults('variant', 'panels');
        Route::get('/tab-content/text', TabContent::class)->name('tab-content-text')->defaults('variant', 'text');
        Route::get('/errors/403', fn () => abort(403))->name('errors-403');
        Route::get('/errors/404', fn () => abort(404))->name('errors-404');
        Route::get('/errors/500', fn () => abort(500))->name('errors-500');
        Route::get('/errors/503', fn () => abort(503))->name('errors-503');
    });
