<?php

declare(strict_types=1);

namespace App\Support\Navigation;

/** A single link within a NavGroup — rendered by admin-sidebar-nav.blade.php. */
final readonly class NavItem
{
    /** @param list<string>|null $activeRoutes route-name patterns (routeIs() wildcards allowed) that mark this item active; defaults to just [$route] */
    public function __construct(
        public string $label,
        public string $route,
        public string $icon,
        public ?array $activeRoutes = null,
    ) {}

    /** @return list<string> */
    public function activeRoutes(): array
    {
        return $this->activeRoutes ?? [$this->route];
    }
}
