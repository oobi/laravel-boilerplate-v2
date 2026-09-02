<?php

declare(strict_types=1);

namespace Concise\ThemeDemo\Navigation;

use App\Enums\SystemPermission;
use App\Models\User;
use App\Support\Navigation\Concerns\HasNavGroupDefaults;
use App\Support\Navigation\NavGroup;
use App\Support\Navigation\NavItem;
use Illuminate\Contracts\Auth\Authenticatable;

class StyleDemoNavGroup implements NavGroup
{
    use HasNavGroupDefaults;

    public function label(): string
    {
        return __('theme-demo::messages.nav_group');
    }

    public function icon(): ?string
    {
        return null;
    }

    /** @return list<NavItem> */
    public function items(): array
    {
        return [
            new NavItem(__('theme-demo::messages.nav_overview'), 'style-demo.index', 'heroicon-o-home'),
            new NavItem(__('theme-demo::messages.nav_tables'), 'style-demo.tables-empty', 'heroicon-o-table-cells', [
                'style-demo.tables-empty', 'style-demo.tables-simple', 'style-demo.tables-maximalist', 'style-demo.tables-wide',
            ]),
            new NavItem(__('theme-demo::messages.nav_filament_table'), 'style-demo.tables-filament-empty', 'heroicon-o-table-cells', [
                'style-demo.tables-filament-empty', 'style-demo.tables-filament-simple', 'style-demo.tables-filament-maximalist', 'style-demo.tables-filament-custom-header', 'style-demo.tables-filament-wide',
            ]),
            new NavItem(__('theme-demo::messages.nav_forms_daisy'), 'style-demo.forms-daisy', 'heroicon-o-pencil-square'),
            new NavItem(__('theme-demo::messages.nav_filament_form'), 'style-demo.forms-filament', 'heroicon-o-pencil-square'),
            new NavItem(__('theme-demo::messages.nav_components'), 'style-demo.components', 'heroicon-o-squares-plus'),
            new NavItem(__('theme-demo::messages.nav_tab_content'), 'style-demo.tab-content-table', 'heroicon-o-window', [
                'style-demo.tab-content-table', 'style-demo.tab-content-form', 'style-demo.tab-content-panels', 'style-demo.tab-content-text',
            ]),
        ];
    }

    public function visible(?Authenticatable $viewer): bool
    {
        return $viewer instanceof User
            && $viewer->can(SystemPermission::ACCESS_ADMIN_PANEL->value);
    }
}
