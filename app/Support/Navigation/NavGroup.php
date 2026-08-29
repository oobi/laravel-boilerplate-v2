<?php

declare(strict_types=1);

namespace App\Support\Navigation;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * A collapsible section of links in the admin sidebar, contributed at
 * runtime via NavRegistry::extend() — the same extension idiom used by
 * PanelRegistry for admin Show/Edit pages — so add-ons never have to edit
 * admin-sidebar-nav.blade.php directly.
 */
interface NavGroup
{
    public function label(): string;

    public function icon(): string;

    /** @return list<NavItem> */
    public function items(): array;

    public function order(): int;

    public function visible(?Authenticatable $viewer): bool;
}
