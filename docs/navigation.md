# Sidebar navigation

How admin sidebar sections are registered, and how an add-on (a Composer
package, a future Teams tier, a local dev tool like `packages/theme-demo`)
contributes its own section without editing `admin-sidebar-nav.blade.php`.

## The idea

`resources/views/layouts/partials/admin-sidebar-nav.blade.php` hardcodes two
things directly: the "Dashboard" link, and the `@can('access admin panel')`
gated "Management" section (Users). Everything else is a **`NavGroup`** —
a small class describing one collapsible sidebar section — resolved at
render time by `App\Support\Navigation\NavRegistry`.

A `NavGroup` implementation contributes:

- `label()` — the section heading shown above its links.
- `icon()` — a heroicon component name (e.g. `heroicon-o-swatch`), rendered
  next to the label on the section's collapse toggle.
- `items()` — a `list<NavItem>`, each a `label` / `route` (the route name its
  link points at) / `icon` (heroicon component name), plus an optional 4th
  `activeRoutes` (`list<string>`, `routeIs()` wildcards allowed) for items
  that should stay highlighted across several routes — e.g. one "DaisyUI
  Tables" item linking to the first of three tabbed routes, matched active
  via `['style-demo.tables-*']`. Defaults to just `[$route]`.
- `order()` / `visible($viewer)` — sorting and conditional display, same
  shape as `ShowPanel`/`FormSection` (see `docs/panels.md`).

## Where nav groups are registered

Only one mechanism: **`NavRegistry::extend(string $groupClass)`**, called
from a service provider's `boot()`. There is no config-file "default set"
like `config/panels.php` — the two core links (Dashboard, Management) are
plain markup in the Blade file, not `NavGroup`s, so `extend()` is purely for
add-ons. To find every registered group, grep the codebase for
`NavRegistry::extend(`.

```php
class ThemeDemoServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        NavRegistry::extend(StyleDemoNavGroup::class);
    }
}
```

## Metadata every group gets for free

`HasNavGroupDefaults` supplies:

| Method | Default | Purpose |
|---|---|---|
| `order()` | `0` | Sort order among registered groups (core Dashboard/Management are unaffected — they're not `NavGroup`s) |
| `visible($viewer)` | `true` | Return `false` to hide conditionally (permissions, environment, feature flags, etc.) |

## Practical example

See `packages/theme-demo/src/Navigation/StyleDemoNavGroup.php` for a real,
working one — registered only in `local`/`testing` environments, gated by
`SystemPermission::ACCESS_ADMIN_PANEL`:

```php
class StyleDemoNavGroup implements NavGroup
{
    use HasNavGroupDefaults;

    public function label(): string
    {
        return __('theme-demo::messages.nav_group');
    }

    public function icon(): string
    {
        return 'heroicon-o-swatch';
    }

    public function items(): array
    {
        return [
            new NavItem(__('theme-demo::messages.nav_overview'), 'style-demo.index', 'heroicon-o-home'),
            // ...
        ];
    }

    public function visible(?Authenticatable $viewer): bool
    {
        return $viewer instanceof User && $viewer->can(SystemPermission::ACCESS_ADMIN_PANEL->value);
    }
}
```

## Rendering

`admin-sidebar-nav.blade.php` resolves the current list once per request via
`NavRegistry::groups(auth()->user())` (already filtered by `visible()` and
sorted by `order()`) and renders each as a collapsible section identical in
markup/behaviour to the core "Management" block (Alpine `x-collapse` +
`$persist` open/closed state, keyed per group so each section remembers its
own state independently).

## Ejecting / hand-tooling the nav

Nothing here is hidden in a package. `NavRegistry` and the Blade loop are
plain app files — if a section ever needs bespoke markup that doesn't fit the
`NavGroup` shape, edit `admin-sidebar-nav.blade.php` directly.
