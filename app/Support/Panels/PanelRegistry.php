<?php

declare(strict_types=1);

namespace App\Support\Panels;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Resolves the ShowPanel/FormSection classes registered for a page key
 * (e.g. "users.show") from config/panels.php, plus any runtime additions
 * from extend() — the mechanism add-ons (like a future Teams tier) use to
 * contribute a panel from their own service provider without editing
 * config/panels.php or the host Livewire component.
 */
class PanelRegistry
{
    /** @var array<string, list<class-string>> */
    protected static array $extensions = [];

    public static function extend(string $key, string $panelClass): void
    {
        static::$extensions[$key][] = $panelClass;
    }

    /** Test/console helper — clears runtime extend() registrations. */
    public static function flush(): void
    {
        static::$extensions = [];
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
        $classes = [
            ...config("panels.{$key}", []),
            ...(static::$extensions[$key] ?? []),
        ];

        return collect($classes)
            ->map(fn (string $class): object => app($class))
            ->filter(fn (object $panel): bool => $panel->visible($subject, $viewer))
            ->sortBy(fn (object $panel): int => $panel->order())
            ->values();
    }
}
