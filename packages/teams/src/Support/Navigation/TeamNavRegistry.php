<?php

declare(strict_types=1);

namespace Concise\Teams\Support\Navigation;

use App\Support\Navigation\Registry\NavGroup;
use App\Support\Navigation\Registry\NavItem;
use Concise\Teams\Models\Team;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * The team area's sidebar registry — separate from the system NavRegistry (R2),
 * but reusing the same NavItem/NavGroup builders so add-ons contribute team nav
 * the identical way. It differs only in resolution: abilities are evaluated
 * against the CURRENT team (`can($ability, $team)`), and item routes are rendered
 * with the team slug by the team sidebar partial. See ~dev/TEAMS_TIER_SCOPE.md §7 / OQ2.
 */
class TeamNavRegistry
{
    /** @var array<string, NavItem> */
    protected static array $items = [];

    /** @var array<string, NavGroup> */
    protected static array $groups = [];

    public static function item(string $name): NavItem
    {
        return static::$items[$name] ??= new NavItem($name);
    }

    public static function group(string $name): NavGroup
    {
        return static::$groups[$name] ??= new NavGroup($name);
    }

    public static function flush(): void
    {
        static::$items = [];
        static::$groups = [];
    }

    /** @return Collection<int, NavItem|NavGroup> */
    public static function resolve(?Authenticatable $viewer, Team $team): Collection
    {
        return collect([...array_values(static::$items), ...array_values(static::$groups)])
            ->filter(fn (NavItem|NavGroup $node): bool => static::canSee($node, $viewer, $team)
                && (! $node instanceof NavGroup || static::visibleItems($node, $viewer, $team) !== []))
            ->sortBy(fn (NavItem|NavGroup $node): int => $node->order)
            ->values();
    }

    /** @return list<NavItem> */
    public static function visibleItems(NavGroup $group, ?Authenticatable $viewer, Team $team): array
    {
        return array_values(array_filter(
            $group->items,
            fn (NavItem $item): bool => static::canSee($item, $viewer, $team),
        ));
    }

    /** A node's `can` ability is checked against the current team, not globally. */
    protected static function canSee(NavItem|NavGroup $node, ?Authenticatable $viewer, Team $team): bool
    {
        return $node->can === null
            || ($viewer !== null && Gate::forUser($viewer)->allows($node->can, $team));
    }
}
