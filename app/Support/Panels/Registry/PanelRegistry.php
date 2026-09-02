<?php

declare(strict_types=1);

namespace App\Support\Panels\Registry;

use App\Support\Panels\Contracts\FormSection;
use App\Support\Panels\Contracts\ShowPanel;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Resolves the ShowPanel/FormSection classes registered for a page key
 * (e.g. "users.show") via for($key)->add(...) — the app (App\Support\Panels\AdminPanels)
 * and add-ons (like a future Teams tier, from their own service provider) both
 * call the same method, without editing the host Livewire component.
 */
class PanelRegistry
{
    /** @var array<string, PanelSet> */
    protected static array $sets = [];

    /** Fetch-or-create the ordered panel list for a page key. */
    public static function for(string $key): PanelSet
    {
        return static::$sets[$key] ??= new PanelSet($key);
    }

    /** Test/console helper — clears runtime registrations. */
    public static function flush(): void
    {
        static::$sets = [];
    }

    /** @return Collection<int, ShowPanel> */
    public static function showPanels(string $key, Model $subject, ?Authenticatable $viewer = null): Collection
    {
        return static::resolve($key, $subject, $viewer)
            ->filter(fn (object $panel): bool => $panel instanceof ShowPanel)
            ->values();
    }

    /** @return Collection<int, FormSection> */
    public static function formSections(string $key, Model $subject, ?Authenticatable $viewer = null): Collection
    {
        return static::resolve($key, $subject, $viewer)
            ->filter(fn (object $panel): bool => $panel instanceof FormSection)
            ->values();
    }

    public static function find(string $key, string $panelKey, Model $subject, ?Authenticatable $viewer = null): ?object
    {
        return static::resolve($key, $subject, $viewer)
            ->first(fn (object $panel): bool => $panel->key() === $panelKey);
    }

    /** @return Collection<int, ShowPanel|FormSection> */
    protected static function resolve(string $key, Model $subject, ?Authenticatable $viewer): Collection
    {
        $classes = static::$sets[$key]?->classes ?? [];

        return collect($classes)
            ->map(fn (string $class): object => app($class))
            ->filter(fn (object $panel): bool => $panel->visible($subject, $viewer))
            ->sortBy(fn (object $panel): int => $panel->order())
            ->values();
    }
}
