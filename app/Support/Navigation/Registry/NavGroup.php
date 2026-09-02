<?php

declare(strict_types=1);

namespace App\Support\Navigation\Registry;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;

/** A collapsible sidebar section — fetch-or-create via NavRegistry::group(), shared by the app and add-ons. */
final class NavGroup
{
    public string $label = '';

    public ?string $icon = null;

    public ?string $can = null;

    public int $order = 0;

    /** @var list<string>|null */
    public ?array $routes = null;

    /** @var list<NavItem> */
    public array $items = [];

    public function __construct(public readonly string $name) {}

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function icon(?string $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    /** Gate ability name required to see this group; null (default) means always visible. */
    public function can(?string $ability): static
    {
        $this->can = $ability;

        return $this;
    }

    public function order(int $order): static
    {
        $this->order = $order;

        return $this;
    }

    /** Explicit route-name patterns that force this group open, overriding the auto-derived union of its items' routes. */
    public function active(string ...$routes): static
    {
        $this->routes = $routes;

        return $this;
    }

    public function add(NavItem ...$items): static
    {
        array_push($this->items, ...$items);

        return $this;
    }

    /** @return list<string> */
    public function activeRoutes(): array
    {
        return $this->routes ?? collect($this->items)
            ->flatMap(fn (NavItem $item): array => $item->activeRoutes())
            ->all();
    }

    public function visible(?Authenticatable $viewer): bool
    {
        return $this->can === null || ($viewer !== null && Gate::forUser($viewer)->allows($this->can));
    }

    /** @return list<NavItem> */
    public function visibleItems(?Authenticatable $viewer): array
    {
        return array_values(array_filter(
            $this->items,
            fn (NavItem $item): bool => $item->visible($viewer),
        ));
    }
}
