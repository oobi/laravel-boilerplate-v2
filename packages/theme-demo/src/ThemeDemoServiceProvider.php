<?php

declare(strict_types=1);

namespace Concise\ThemeDemo;

use App\Enums\SystemPermission;
use App\Support\Navigation\Registry\NavItem;
use App\Support\Navigation\Registry\NavRegistry;
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

        NavRegistry::group('style-demo')
            ->label(__('theme-demo::messages.nav_group'))
            ->can(SystemPermission::ACCESS_ADMIN_PANEL->value)
            ->order(1000) // intentionally last, so it doesn't clutter the nav for normal users
            ->add(
                NavItem::make('style-demo-overview')
                    ->label(__('theme-demo::messages.nav_overview'))
                    ->route('style-demo.index')
                    ->icon('heroicon-o-home'),
                NavItem::make('style-demo-tables')
                    ->label(__('theme-demo::messages.nav_tables'))
                    ->route('style-demo.tables-empty')
                    ->icon('heroicon-o-table-cells')
                    ->active('style-demo.tables-empty', 'style-demo.tables-simple', 'style-demo.tables-maximalist', 'style-demo.tables-wide'),
                NavItem::make('style-demo-filament-table')
                    ->label(__('theme-demo::messages.nav_filament_table'))
                    ->route('style-demo.tables-filament-empty')
                    ->icon('heroicon-o-table-cells')
                    ->active('style-demo.tables-filament-empty', 'style-demo.tables-filament-simple', 'style-demo.tables-filament-maximalist', 'style-demo.tables-filament-custom-header', 'style-demo.tables-filament-wide'),
                NavItem::make('style-demo-forms-daisy')
                    ->label(__('theme-demo::messages.nav_forms_daisy'))
                    ->route('style-demo.forms-daisy')
                    ->icon('heroicon-o-pencil-square')
                    ->active('style-demo.forms-daisy', 'style-demo.forms-daisy-standard'),
                NavItem::make('style-demo-filament-form')
                    ->label(__('theme-demo::messages.nav_filament_form'))
                    ->route('style-demo.forms-filament')
                    ->icon('heroicon-o-pencil-square'),
                NavItem::make('style-demo-components')
                    ->label(__('theme-demo::messages.nav_components'))
                    ->route('style-demo.components')
                    ->icon('heroicon-o-squares-plus'),
                NavItem::make('style-demo-filament-components')
                    ->label(__('theme-demo::messages.nav_filament_components'))
                    ->route('style-demo.components-filament')
                    ->icon('heroicon-o-squares-plus'),
                NavItem::make('style-demo-modals-daisy')
                    ->label(__('theme-demo::messages.nav_modals_daisy'))
                    ->route('style-demo.modals-daisy')
                    ->icon('heroicon-o-window'),
                NavItem::make('style-demo-modals-filament')
                    ->label(__('theme-demo::messages.nav_modals_filament'))
                    ->route('style-demo.modals-filament')
                    ->icon('heroicon-o-window'),
                NavItem::make('style-demo-tab-content')
                    ->label(__('theme-demo::messages.nav_tab_content'))
                    ->route('style-demo.tab-content-table')
                    ->icon('heroicon-o-window')
                    ->active('style-demo.tab-content-table', 'style-demo.tab-content-form', 'style-demo.tab-content-panels', 'style-demo.tab-content-text'),
            );
    }
}
