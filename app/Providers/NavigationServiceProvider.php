<?php

declare(strict_types=1);

namespace App\Providers;

use App\Support\Navigation\AdminNav;
use Illuminate\Support\ServiceProvider;

class NavigationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        AdminNav::define();
    }
}
