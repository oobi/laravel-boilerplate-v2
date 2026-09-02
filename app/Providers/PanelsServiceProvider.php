<?php

declare(strict_types=1);

namespace App\Providers;

use App\Support\Panels\AdminPanels;
use Illuminate\Support\ServiceProvider;

class PanelsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        AdminPanels::define();
    }
}
