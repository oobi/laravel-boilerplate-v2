# Project guidelines

Laravel 12 admin boilerplate. Everything lives in this app directly — no
first-party Composer packages for core features (see [docs/architecture.md](../docs/architecture.md)
for the full stack rationale).

## Where things live

| You're adding... | Goes in | Docs |
|---|---|---|
| A sidebar link/section | `App\Support\Navigation\AdminNav` via `NavRegistry` | [docs/navigation.md](../docs/navigation.md) |
| A card/field on a Users Show/Edit page | `app/Panels/Users/*` registered in `App\Support\Panels\AdminPanels` | [docs/panels.md](../docs/panels.md) |
| A new `bp:` artisan command | `app/Console/Commands/*`, document it | [docs/commands.md](../docs/commands.md) |
| A Livewire full-page component | `app/Livewire/*` | — |
| RBAC checks | `App\Enums\SystemRole` / `SystemPermission` + `Gate::authorize()` in the component's `mount()` | `~/memories/repo/impersonation.md` has impersonation-specific notes |

Add-ons (local dev packages like `packages/theme-demo`, a future Teams tier)
extend core via the same registries from their own service provider's
`boot()` — never by editing `AdminNav`, `AdminPanels`, or Blade files
directly. Grep `NavRegistry::` / `PanelRegistry::for(` to see everything
currently registered.

## Conventions

- Tests are PHPUnit classes only (not Pest) — `php artisan make:test --phpunit`.
- Run `vendor/bin/pint --dirty` before finalizing PHP changes.
- Route names for panel/nav-linked pages must be flat (`resource.action`, not
  `resource.sub.action`) — `App\Support\Breadcrumbs` derives the parent
  crumb from `Str::beforeLast($routeName, '.')`.
- No shade-ramp Tailwind utilities (`bg-primary-500` etc.) — only the 8
  daisyUI semantic colors (`bg-primary`, `text-primary-content`, ...) plus
  `bg-soft-{color}` / `avatar-{color}` / `ui-banner-{color}` from
  `resources/css/theme/components/ui/colors.css`.
