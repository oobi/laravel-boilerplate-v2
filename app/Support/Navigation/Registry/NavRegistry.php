<?php

declare(strict_types=1);

namespace App\Support\Navigation\Registry;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;

/**
 * The one sidebar registry — the app (App\Support\Navigation\AdminNav) and
 * add-ons (via their own service provider's boot()) both call item()/group()
 * to contribute nodes; nothing else touches admin-sidebar-nav.blade.php.
 */
class NavRegistry
{
    /** @var array<string, NavItem> */
    protected static array $items = [];

    /** @var array<string, NavGroup> */
    protected static array $groups = [];

    /** Fetch-or-create a standalone top-level link. */
    public static function item(string $name): NavItem
    {
        return static::$items[$name] ??= new NavItem($name);
    }

    /** Fetch-or-create a collapsible section — the extension point for add-ons. */
    public static function group(string $name): NavGroup
    {
        return static::$groups[$name] ??= new NavGroup($name);
    }

    /** Test/console helper — clears runtime registrations. */
    public static function flush(): void
    {
        static::$items = [];
        static::$groups = [];
    }

    /** @return Collection<int, NavItem|NavGroup> */
    public static function resolve(?Authenticatable $viewer): Collection
    {
        return collect([...array_values(static::$items), ...array_values(static::$groups)])
            ->filter(fn (NavItem|NavGroup $node): bool => $node->visible($viewer)
                && (! $node instanceof NavGroup || $node->visibleItems($viewer) !== []))
            ->sortBy(fn (NavItem|NavGroup $node): int => $node->order)
            ->values();
    }
}
