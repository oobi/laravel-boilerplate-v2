<?php

declare(strict_types=1);

namespace App\Support\Navigation;

/** A single link within a NavGroup — rendered by admin-sidebar-nav.blade.php. */
final readonly class NavItem
{
    public function __construct(
        public string $label,
        public string $route,
        public string $icon,
    ) {}
}
