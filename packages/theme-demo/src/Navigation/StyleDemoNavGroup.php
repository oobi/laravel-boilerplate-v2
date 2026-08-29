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

    public function icon(): string
    {
        return 'heroicon-o-swatch';
    }

    /** @return list<NavItem> */
    public function items(): array
    {
        return [
            new NavItem(__('theme-demo::messages.nav_overview'), 'style-demo.index', 'heroicon-o-home'),
            new NavItem(__('theme-demo::messages.nav_tables_empty'), 'style-demo.tables-empty', 'heroicon-o-table-cells'),
            new NavItem(__('theme-demo::messages.nav_tables_simple'), 'style-demo.tables-simple', 'heroicon-o-table-cells'),
            new NavItem(__('theme-demo::messages.nav_tables_maximalist'), 'style-demo.tables-maximalist', 'heroicon-o-table-cells'),
            new NavItem(__('theme-demo::messages.nav_forms_daisy'), 'style-demo.forms-daisy', 'heroicon-o-pencil-square'),
            new NavItem(__('theme-demo::messages.nav_components'), 'style-demo.components', 'heroicon-o-squares-plus'),
        ];
    }

    public function visible(?Authenticatable $viewer): bool
    {
        return $viewer instanceof User
            && $viewer->can(SystemPermission::ACCESS_ADMIN_PANEL->value);
    }
}
