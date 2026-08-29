<?php

declare(strict_types=1);

namespace Concise\ThemeDemo;

use App\Support\Navigation\NavRegistry;
use Concise\ThemeDemo\Navigation\StyleDemoNavGroup;
use Illuminate\Support\ServiceProvider;

/**
 * Auto-discovered the moment this package is composer-required (require-dev
 * only in the host app, so it never installs in `composer install --no-dev`).
 * Routes/nav additionally only register in local/testing environments —
 * defense in depth on top of the require-dev boundary.
 */
class ThemeDemoServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'theme-demo');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'theme-demo');

        if (! app()->environment('local', 'testing')) {
            return;
        }

        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        NavRegistry::extend(StyleDemoNavGroup::class);
    }
}
