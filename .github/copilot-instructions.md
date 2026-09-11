# Project guidelines

Laravel 12 admin boilerplate. Everything lives in this app directly — no
first-party Composer packages for core features (see [docs/architecture.md](../docs/architecture.md)
for the full stack rationale).

This project uses [Laravel Boost](https://laravel.com/docs/boost) — see
[AGENTS.md](../AGENTS.md) for its generic Laravel/Livewire/testing guidelines
and tool usage (`search-docs`, `tinker`, `database-query`, etc.), and
[.ai/rules/index.md](../.ai/rules/index.md) for inferred, path-scoped
conventions (read the rule file matching whatever path you're editing before
you start). This file stays the source of truth for anything specific to
*this* app's architecture.

## Guiding principle — this is a boilerplate, so hold it to the highest bar

Every choice here is inherited by every project started from it, so a shortcut
taken once is a compromise shipped everywhere. Default to best practice, not the
quickest path: code and features should be **secure** (authorize and validate at
the boundary, no bypassable guards), **standards-compliant** (framework-idiomatic,
follow the conventions in this repo rather than working around them),
**accessible** (semantic markup, labels, focus and keyboard support, sufficient
contrast), **mobile-friendly** (responsive, no reliance on hover, reachable
without excessive scrolling), and **beautiful, considered UX** (no dead-ends,
clear state, sensible defaults). When a proper solution costs more than a hack,
pay for the proper solution — or raise the trade-off explicitly rather than
quietly baking a compromise into the foundation.

## Where things live

| You're adding... | Goes in | Docs |
|---|---|---|
| A sidebar link/section | `App\Support\Navigation\AdminNav` via `NavRegistry` | [docs/navigation.md](../docs/navigation.md) |
| A card/field on a Users Show/Edit page | `app/Panels/Users/*` registered in `App\Support\Panels\AdminPanels` | [docs/panels.md](../docs/panels.md) |
| A new `bp:` artisan command | `app/Console/Commands/*`, document it | [docs/commands.md](../docs/commands.md) |
| A Livewire full-page component | `app/Livewire/{Area}/{Domain}/*` (e.g. `app/Livewire/Admin/Users/*`) | — |
| RBAC checks | Global abilities: `$user->hasPermissionTo(SystemPermission::X->value)` (spatie/laravel-permission). Per-instance User abilities (edit/activate/delete a specific user): `App\Policies\UserPolicy` via `Gate::authorize('ability', $target)`. Super admin bypasses both via a global `Gate::before()` — it's a hardcoded flag, never a role | [.ai/rules/policies.md](../.ai/rules/policies.md), `~/memories/repo/impersonation.md` has impersonation-specific notes |
| A new role or permission | Admin > Roles screen (`app/Livewire/Admin/Roles/*`) — never hardcode a new role in PHP. New *permissions* are still code (a `SystemPermission` case + seeder entry); role -> permission assignment is admin-configurable | [docs/permissions.md](../docs/permissions.md), [.ai/rules/policies.md](../.ai/rules/policies.md) |
| A new *family* of roles (a tab on the Roles screen) | An `App\Support\Roles\RoleScope` registered via `RoleScopeRegistry::register()` — core's in `AdminRoleScopes`, add-ons from their own provider (e.g. the teams tier's `TeamRoleScope`) | [docs/permissions.md](../docs/permissions.md) |

## Group by domain, not by type

This boilerplate is the *start* of a bigger app, not the whole app — pick an
organisation scheme now that still makes sense once a domain has 5+ files.
Once a feature area (e.g. "Users") has more than one Livewire component,
create a `{Domain}` subfolder and mirror it across every layer:

- `app/Livewire/Admin/Users/{CreateUser,EditUser,ListUsers,ShowUser}.php`
- `app/Panels/Users/*` (already established)
- `resources/views/livewire/admin/users/*.blade.php`
- `tests/Feature/Admin/Users/*Test.php`

Single-file areas stay flat at their parent level (`app/Livewire/Home.php`,
`app/Livewire/Admin/Dashboard.php`) — don't create a one-file subfolder
pre-emptively. Split it out once a second related file shows up.

Add-ons (local dev packages like `packages/theme-demo`, a future Teams tier)
extend core via the same registries from their own service provider's
`boot()` — never by editing `AdminNav`, `AdminPanels`, or Blade files
directly. Grep `NavRegistry::` / `PanelRegistry::for(` to see everything
currently registered.

## Conventions

- Tests are PHPUnit classes only (not Pest) — `php artisan make:test --phpunit`.
- Run `vendor/bin/pint --dirty --format agent` before finalizing PHP changes.
- Route names for panel/nav-linked pages must be flat (`resource.action`, not
  `resource.sub.action`) — `App\Support\Breadcrumbs` derives the parent
  crumb from `Str::beforeLast($routeName, '.')`.
- No shade-ramp Tailwind utilities (`bg-primary-500` etc.) — only the 8
  daisyUI semantic colors (`bg-primary`, `text-primary-content`, ...) plus
  `bg-soft-{color}` / `avatar-{color}` / `ui-banner-{color}` from
  `resources/css/theme/components/ui/colors.css`.

## Coding standards

Full source of truth: [_documentation/CODING_STANDARDS.md](../../_documentation/CODING_STANDARDS.md).
That doc is generic across projects and assumes a controller+Form
Request+Policy stack — this app has none of those (no `app/Http/Requests`,
no `app/Policies`). Here, validation lives in Filament Schema field rules on
Livewire components and authorization is `Gate::define()`-only; see
[.ai/rules/livewire.md](../.ai/rules/livewire.md) and
[.ai/rules/providers.md](../.ai/rules/providers.md). Salient points that
still apply:

- Extract to an **Action** once logic is >~10 lines, multi-step, or
  reusable; promote to a **Service** only when Actions share state/a
  third-party client.
- Fixed-value fields are **string-backed PHP enums** + a `string` migration
  column — never a MySQL native `ENUM` column (breaks SQLite tests, needs
  `ALTER TABLE` to change) and never a bare int/magic string.
- Never mass-assign unvalidated request input.
- Admin/maintenance one-off tasks are Artisan commands, never a hidden
  GET route. Destructive commands need a confirmation prompt + `--dry-run`.
- One PR = one concern, aim under ~400 lines diff; log bugs as GitHub issues
  before fixing, reference them in the commit.
- Don't commit code unless explicitly asked. Make the change and leave it
  uncommitted for review; only run `git commit` (or push, or open a PR) when
  the user asks for it in so many words.
- Run tests after every change, and before pushing. Use `php artisan test`
