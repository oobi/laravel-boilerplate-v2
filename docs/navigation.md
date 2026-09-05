# Sidebar navigation

How the admin sidebar menu is defined, and how an add-on (a Composer
package, a future Teams tier, a local dev tool like `packages/theme-demo`)
contributes its own links or section without editing
`admin-sidebar-nav.blade.php`.

## The idea

One registry, `App\Support\Navigation\Registry\NavRegistry`, owns the entire
sidebar — both the app's own links and everything add-ons contribute. There
is no config file and no interface to implement: the app curates its menu
declaratively in `App\Support\Navigation\AdminNav` using a small fluent
builder, and add-ons call the exact same builder methods from their own
service provider's `boot()`.

```php
// app/Support/Navigation/AdminNav.php — the file you edit to curate the menu
class AdminNav
{
    public static function define(): void
    {
        NavRegistry::item('dashboard')
            ->label(__('Dashboard'))
            ->route('dashboard')
            ->icon('heroicon-o-squares-2x2')
            ->order(0);

        NavRegistry::group('management')
            ->label(__('Management'))
            ->can('access admin panel')
            ->order(10)
            ->add(
                NavItem::make('users')
                    ->label(__('Users'))
                    ->route('users.index')
                    ->icon('heroicon-o-user')
                    ->active('users.*'),
            );
    }
}
```

`AdminNav::define()` runs once per request from `NavigationServiceProvider::boot()`.

## The two node types

- **`NavItem`** — a single link. Fetched-or-created at the top level via
  `NavRegistry::item($name)`, or built with `NavItem::make($name)` to `add()`
  into a group.
- **`NavGroup`** — a collapsible section containing a list of `NavItem`s.
  Fetched-or-created via `NavRegistry::group($name)` — calling this with an
  existing name returns the *same* group, which is how add-ons extend a
  core section like `management` instead of duplicating it.

Both are plain fluent objects — no interface to implement:

| Method | Applies to | Purpose |
|---|---|---|
| `label(string)` | item, group | Text shown in the sidebar |
| `icon(string)` / `icon(?string)` | item, group | Heroicon component name (group icon is optional) |
| `route(string)` | item | Named route the link points at |
| `active(string ...$routes)` | item, group | `routeIs()` patterns that mark it active; item defaults to `[$route]`, group defaults to the **union of its items' active routes** |
| `can(?string $ability)` | item, group | Gate ability name required to see it; `null` (default) means always visible |
| `order(int)` | item, group | Sort position among all top-level nodes |
| `add(NavItem ...$items)` | group only | Appends items to the group |

## Why groups auto-open across route boundaries

A group's active state is the **union of all its items' `active()` routes**
unless you give the group an explicit `active(...)` override. This is what
lets a "Management" section stay open whether the current route is
`users.*` or (once added) `teams.*` — you never have to maintain that list
by hand in more than one place.

## Where add-ons register

Only one mechanism: call `NavRegistry::item()` / `NavRegistry::group()` from
a service provider's `boot()` — the same calls `AdminNav` itself makes. To
find every registration, grep the codebase for `NavRegistry::`.

```php
class ThemeDemoServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (! app()->environment('local', 'testing')) {
            return;
        }

        // Register its own section...
        NavRegistry::group('style-demo')
            ->label(__('theme-demo::messages.nav_group'))
            ->can(SystemPermission::ACCESS_ADMIN_PANEL->value)
            ->order(20)
            ->add(
                NavItem::make('style-demo-overview')
                    ->label(__('theme-demo::messages.nav_overview'))
                    ->route('style-demo.index')
                    ->icon('heroicon-o-home'),
            );

        // ...or extend an existing one:
        // NavRegistry::group('management')->add(NavItem::make('audit')->label('Audit')->route('audit.index'));
    }
}
```

See `packages/theme-demo/src/ThemeDemoServiceProvider.php` for the real,
working example above (registered only in `local`/`testing` environments).

## Permissions

`can()` takes a plain gate-ability-name **string**, resolved via
`Gate::forUser($viewer)->allows($ability)` — either a `SystemPermission`
value (checked via spatie/laravel-permission's own `Gate::before`, granted
per-role on the Roles admin screen) or an explicit `Gate::define()` for a
one-off ability like `manage roles` (see `AppServiceProvider`). No closures: a
group or an item is either always visible (`can()` never called) or gated by
one named ability. A group with a `can()` gate that fails is hidden entirely; a group
that passes but ends up with zero *visible* items (all its items' own
`can()` checks failed) is also dropped from render — nothing to expand into
an empty section.

Dynamic conditions that aren't a simple gate check (environment checks,
feature flags) are just a plain `if` guard around the registration call, as
shown in `ThemeDemoServiceProvider` above — not a mechanism the registry
needs to know about.

## Rendering

`admin-sidebar-nav.blade.php` resolves the current list once per request via
`NavRegistry::resolve(auth()->user())` (already filtered by `can()` and
sorted by `order()`) and renders each node through one of two dumb Blade
components: `<x-nav-item>` for a link, `<x-nav-group>` for a collapsible
section (Alpine `x-collapse` + `$persist` open/closed state, keyed per
group so each section remembers its own state independently). All the
padding/border/chevron/Alpine markup lives in those two components — no
hand-written markup is duplicated per section.

## Ejecting / hand-tooling the nav

Nothing here is hidden in a package. `NavRegistry`, `AdminNav`, and the
Blade components are plain app files — if a section ever needs bespoke
markup that doesn't fit `<x-nav-item>`/`<x-nav-group>`, edit
`admin-sidebar-nav.blade.php` directly.
