<?php

declare(strict_types=1);

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Derives a breadcrumb trail from the current route name, following the
 * `{resource}.{action}` convention every resource route group already uses
 * (users.index / users.create / users.show / users.edit, etc). A resource
 * gets a linked parent crumb automatically the moment it registers a
 * sibling `{resource}.index` route — no per-page configuration needed, so
 * new resources get working breadcrumbs for free as long as they follow
 * the same route-naming convention.
 */
class Breadcrumbs
{
    /** @var array<string, Closure|string> */
    protected static array $labels = [];

    /**
     * Name a resource's parent crumb explicitly — for an add-on whose label
     * isn't in `lang/en/admin.php` (e.g. the teams tier, whose word for a
     * team is configurable). A Closure is resolved at render time.
     */
    public static function label(string $resource, Closure|string $label): void
    {
        static::$labels[$resource] = $label;
    }

    /** Test/console helper — clears runtime registrations. */
    public static function flush(): void
    {
        static::$labels = [];
    }

    /**
     * @param  array{label: string, url: ?string}|null  $root  Overrides the root
     *                                                         crumb (defaults to the "Admin" home). Areas that aren't the system admin
     *                                                         (e.g. a team area) pass their own root so the trail reads in their context.
     * @param  bool  $withResource  Whether to auto-derive the `{resource}.index`
     *                              parent crumb from the route name.
     * @return list<array{label: string, url: ?string}>
     */
    public static function trail(?array $root = null, bool $withResource = true): array
    {
        $crumbs = [
            $root ?? ['label' => __('admin.breadcrumb_root'), 'url' => null],
        ];

        $resource = $withResource ? static::resource() : null;
        $page = static::sectionText('page-title') ?: __('Dashboard');

        if ($resource && Route::has("{$resource}.index")) {
            $label = static::resourceLabel($resource);
            $isIndex = Route::is("{$resource}.index");

            // A list page named after its resource is that crumb, so it isn't repeated;
            // an index with its own title keeps the crumb, unlinked.
            if (! ($isIndex && $label === $page)) {
                $crumbs[] = ['label' => $label, 'url' => $isIndex ? null : route("{$resource}.index")];
            }
        }

        $parent = static::sectionText('page-parent');

        if ($parent !== '') {
            $crumbs[] = ['label' => $parent, 'url' => static::sectionText('page-parent-url') ?: null];
        }

        $crumbs[] = ['label' => $page, 'url' => null];

        return $crumbs;
    }

    protected static function resource(): ?string
    {
        $routeName = Route::currentRouteName();

        if (! $routeName || ! str_contains($routeName, '.')) {
            return null;
        }

        return Str::beforeLast($routeName, '.');
    }

    protected static function resourceLabel(string $resource): string
    {
        if (isset(static::$labels[$resource])) {
            return (string) value(static::$labels[$resource]);
        }

        $key = "admin.{$resource}";

        return Lang::has($key) ? __($key) : Str::headline(str_replace('.', ' ', $resource));
    }

    /**
     * A view section as plain text. The inline `@section('name', $value)` form
     * escapes its value, so decode it here or it would be escaped twice on output.
     */
    public static function sectionText(string $name): string
    {
        $content = strip_tags((string) app('view')->yieldContent($name));

        return trim(html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}
