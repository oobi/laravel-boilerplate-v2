<?php

declare(strict_types=1);

namespace App\Support\Navigation;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;

/**
 * Resolves NavGroup classes registered at runtime via extend() — how
 * add-ons (e.g. this app's style-demo dev package) contribute a sidebar
 * section without editing admin-sidebar-nav.blade.php. Mirrors PanelRegistry.
 */
class NavRegistry
{
    /** @var list<class-string<NavGroup>> */
    protected static array $groups = [];

    public static function extend(string $groupClass): void
    {
        static::$groups[] = $groupClass;
    }

    /** Test/console helper — clears runtime extend() registrations. */
    public static function flush(): void
    {
        static::$groups = [];
    }

    /** @return Collection<int, NavGroup> */
    public static function groups(?Authenticatable $viewer): Collection
    {
        return collect(static::$groups)
            ->map(fn (string $class): object => app($class))
            ->filter(fn (object $group): bool => $group->visible($viewer))
            ->sortBy(fn (object $group): int => $group->order())
            ->values();
    }
}
