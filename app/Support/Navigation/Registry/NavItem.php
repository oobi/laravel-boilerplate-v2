<?php

declare(strict_types=1);

namespace App\Support\Navigation\Registry;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;

/** A single link, either standalone or added to a NavGroup — fluent, no config file. */
final class NavItem
{
    public string $label = '';

    public string $route = '';

    public string $icon = '';

    /** @var list<string>|null */
    public ?array $routes = null;

    public ?string $can = null;

    public int $order = 0;

    public function __construct(public readonly string $name) {}

    public static function make(string $name): self
    {
        return new self($name);
    }

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function route(string $route): static
    {
        $this->route = $route;

        return $this;
    }

    public function icon(string $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    /** Route-name patterns (routeIs() wildcards allowed) that mark this item active; defaults to just the item's route. */
    public function active(string ...$routes): static
    {
        $this->routes = $routes;

        return $this;
    }

    /** Gate ability name required to see this item; null (default) means always visible. */
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

    /** @return list<string> */
    public function activeRoutes(): array
    {
        return $this->routes ?? [$this->route];
    }

    public function visible(?Authenticatable $viewer): bool
    {
        return $this->can === null || ($viewer !== null && Gate::forUser($viewer)->allows($this->can));
    }
}
