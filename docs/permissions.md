# Permissions

How the app's RBAC vocabulary is defined, how a role gets its permissions,
and how to add a new permission. See `.ai/rules/policies.md` for the
Policy-level conventions this builds on.

## The fixed vocabulary: `App\Enums\SystemPermission`

Every real, code-checked ability in the app is a case on the
`SystemPermission` string-backed enum (`app/Enums/SystemPermission.php`):

```php
enum SystemPermission: string
{
    case ACCESS_ADMIN_PANEL = 'access admin panel';
    case MANAGE_SYSTEM_SETTINGS = 'manage system settings';
    case VIEW_SYSTEM_ANALYTICS = 'view system analytics';
    case MANAGE_USERS = 'manage users';
    case SUSPEND_USERS = 'suspend users';
    case DELETE_USERS = 'delete users';
    case IMPERSONATE_USERS = 'impersonate users';

    public function label(): string { /* ... */ }
    public function category(): string { /* ... */ }
}
```

This enum is the single source of truth — permission strings are never
hand-typed at a call site. Each case also declares:

- **`label()`** — the human-readable name shown on the Manage Roles screen.
- **`category()`** — which functional-area tab the permission is grouped
  under (e.g. `User Management`, `System Administration`). `byCategory()`
  groups all cases by this for the roles form; adding a new category is just
  a new string returned from `category()` — a tab for it appears
  automatically, no other wiring needed.

Permission strings here are plain, natural-language phrases (`manage users`,
`access admin panel`), not a `resource.action` convention like Filament
Shield's `view_users`/`create_users`. Renaming any of them to a different
scheme would mean a migration to rewrite the seeded `permissions` rows plus
every call site below — treat that as a deliberate, separate change.

## Seeding: one Permission row per case

`database/seeders/PermissionSeeder.php` calls `Permission::findOrCreate()`
for every `SystemPermission` case. It's idempotent, so adding a new case and
re-running the seeder (or `migrate:fresh --seed`) is all that's needed to
make a new permission assignable — nothing else registers permission rows.

```php
foreach (SystemPermission::cases() as $permission) {
    Permission::findOrCreate($permission->value);
}
```

`tests/TestCase.php` seeds this once per `RefreshDatabase` migration
(`afterRefreshingDatabase()`), so feature tests never need their own
`$this->seed()` call for permissions to exist.

## Checking a permission: `Gate::authorize()` / `checkPermissionTo()`

There's no `Gate::define()` per `SystemPermission` case — spatie/laravel-
permission registers its own `Gate::before()` that resolves any ability name
against the acting user's assigned permissions. So a plain:

```php
Gate::authorize(SystemPermission::ACCESS_ADMIN_PANEL->value);
```

...inside a Livewire component's `mount()` (see `ListUsers`/`ShowUser`) or a
route-group `can:` middleware (see `routes/web.php`'s admin group) is enough
— no extra registration required for a `SystemPermission` case to become
checkable.

Inside a Policy or other app code checking a role/user's permission
directly, always call **`checkPermissionTo()`**, never `hasPermissionTo()` —
the latter throws `PermissionDoesNotExist` for an unseeded or
guard-mismatched permission (confirmed to happen even for an already-seeded
permission inside a Livewire component test) — turning an authorization
check into a 500 instead of a deny. See `.ai/rules/policies.md`.

Super admins bypass all of this via a single, hardcoded
`Gate::before()` in `AppServiceProvider::registerAuthorization()` — it's a
boolean flag on `User`, never a role, and is excluded from a small list of
abilities (`impersonate`, `assignRole`, self-targeting `delete`/
`toggleActive`/`grantSuperAdmin`) that must always defer to their real
Policy check instead.

## Assigning permissions to a role: the Manage Roles screen only

Role -> permission assignment is 100% admin-configurable and only happens in
one place: `App\Livewire\Admin\Roles\ManageRoles` (route names `roles.index`
/ `roles.edit`, both render the same component — there's no separate roles
list page, a dropdown at the top switches which role is being edited). It
renders one tab per `SystemPermission::category()`, each with a "select all"
toggle and a checkbox list, and calls `$role->syncPermissions(...)` on save.

Creating a new role (`App\Livewire\Admin\Roles\CreateRole`, route
`roles.create`) uses the same tabbed schema, built by the shared
`App\Livewire\Admin\Roles\Concerns\HasPermissionsSchema` trait — this is the
one place that knows how to turn `SystemPermission::byCategory()` into
Filament form components, fill a role's current permissions into per-tab
state, and flatten per-tab state back into a single permission list on save.
Both Livewire components use it so the tab layout/behaviour can't drift
between "create" and "edit".

The **`manage roles`** ability that gates both screens is deliberately
hardcoded super-admin-only in `AppServiceProvider` (`Gate::define('manage
roles', fn (User $user) => $user->isSuperAdmin())`), never itself a
`SystemPermission` — otherwise a role could grant itself broader access by
editing its own definition.

Assigning a *role* to a *user* is a separate ability (`assignRole`) from
assigning *permissions* to a *role* — see `EditUser::manageRolesAction()`
and `UserPolicy::assignRole()`'s docblock for why that's never folded into
the generic "update a user" ability.

## Adding a new permission

1. Add a case to `SystemPermission` with a `label()` and `category()` (reuse
   an existing category string to land it in an existing tab, or introduce a
   new one to get a new tab for free).
2. Re-run `PermissionSeeder` (`php artisan db:seed --class=PermissionSeeder`,
   or `migrate:fresh --seed` in dev).
3. Check it somewhere with `Gate::authorize()` / `Gate::allows()` /
   `checkPermissionTo()`, same as any existing case.

No route, form, or Manage Roles change is required — the new permission
appears automatically as a checkbox under its category's tab.
