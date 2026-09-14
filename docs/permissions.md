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
    case VIEW_USERS = 'view users';
    case MANAGE_USERS = 'manage users';
    case SUSPEND_USERS = 'suspend users';
    case DELETE_USERS = 'delete users';
    case IMPERSONATE_USERS = 'impersonate users';

    public function label(): string { /* ... */ }
    public function category(): string { /* ... */ }
    public function implies(): array { /* ... */ }
}
```

### Shape: panel entry, a read floor per area, actions on top

`access admin panel` is **entry only** — the admin shell, the dashboard and
its navigation. It does not open any area's data. Each area has its own read
floor: `view users` opens the user list and profiles read-only, `view system
analytics` the analytics module, and an add-on's area brings its own (the teams
tier's `view teams`). The action permissions of an area sit on top of its
floor: `manage users`, `suspend users`, `delete users`, `impersonate users`
each unlock their acts inside the users area.

Why: a role that reaches the panel for one module must not browse another's
data. A stats-only admin holds `access admin panel` + `view system analytics`
and never sees a user's name or address; a support desk holds `view users` +
`manage users` + `suspend users` and nothing about teams.

**Implications keep that shape honest.** `SystemPermission::implies()`
declares, one hop at a time, that every action carries its area's read floor
and every read floor carries panel entry. It is applied when a role is
*written* — the Roles form, `SystemRolesSeeder`, `UserFactory::withPermission()`
— through `SystemPermission::withImplied()` (transitive, via
`App\Support\Roles\Implications`), so a stored grant is always reachable and
`checkPermissionTo()` and the Gate agree. The form shows the chain: tick
`delete users` and `view users` and `access admin panel` tick and lock with it,
each saying "Included with …" on hover. Never resolve implications at check
time; a check asks for exactly one permission. Any new path that grants
system permissions must call `withImplied()`. Changing the map later is a code
change plus `php artisan bp:roles:sync-implications` with that deploy (see
`docs/commands.md`).

This enum is the single source of truth — permission strings are never
hand-typed at a call site. Each case also declares:

- **`label()`** — the human-readable name shown on the Manage Roles screen.
- **`category()`** — which functional-area tab the permission is grouped
  under (e.g. `User Management`, `System Administration`). `byCategory()`
  groups all cases by this for the roles form; adding a new category is just
  a new string returned from `category()` — a tab for it appears
  automatically, no other wiring needed.

Both `label()` and `category()` read from `lang/en/permissions.php` via `__()`,
so a localised install translates them without touching authorization — the
permission *value* (`manage users`, …) is the fixed identifier and never
changes; only the displayed string does. (The teams tier's own permissions
relabel through `team_trans` instead — see `.ai/rules/teams.md`.)

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

### Default roles: `SystemRolesSeeder`

A fresh install also gets two system roles from
`database/seeders/SystemRolesSeeder.php`: **Administrator** (every
`SystemPermission` — the gap from a super admin is exactly the acts that
aren't permissions: managing roles, granting super admin, direct password
resets) and a limited **Support** (manage and suspend users, impersonate —
listed as the actions alone; the seeder writes them closed over `implies()`,
so the role also holds `view users` and `access admin panel`). They are seeded **only into an empty set of system roles**;
after that the database is the source of truth and renaming, deleting or
replacing them is safe — nothing resurrects them. Edit the seeder's
`defaults()` to change what a fresh install ships; `UserFactory::support()`
reads the same definition so the test fixture can't drift from it. The teams
tier's `TeamRolesSeeder` does the same for team roles.

`bp:make-admin --administrator` puts the first operator on the Administrator
role instead of the super-admin flag (see `docs/commands.md`).

## Checking a permission: `Gate::authorize()` / `checkPermissionTo()`

There's no `Gate::define()` per `SystemPermission` case — spatie/laravel-
permission registers its own `Gate::before()` that resolves any ability name
against the acting user's assigned permissions. So a plain:

```php
Gate::authorize(SystemPermission::ACCESS_ADMIN_PANEL->value);
```

...in a route-group `can:` middleware (see `routes/web.php`'s admin group,
which gates the whole shell on panel entry) or a Livewire component's
`mount()` (`ListUsers`/`ShowUser` authorize the area's read floor,
`VIEW_USERS`) is enough — no extra registration required for a
`SystemPermission` case to become checkable.

Inside a Policy or other app code checking a role/user's permission
directly, always call **`checkPermissionTo()`**, never `hasPermissionTo()` —
the latter throws `PermissionDoesNotExist` for an unseeded or
guard-mismatched permission (confirmed to happen even for an already-seeded
permission inside a Livewire component test) — turning an authorization
check into a 500 instead of a deny. See `.ai/rules/policies.md`.

On a `User`, ask **`$user->hasSystemPermission(SystemPermission::X)`** rather
than `checkPermissionTo()` directly: it goes through the Gate, so the
super-admin bypass applies. System roles are stock spatie with its teams
feature **off** — a team role is never a spatie assignment on the user; it
hangs off the membership pivot (`team_user_role`) and is read by
`Team::memberHasPermission()`. So a `SystemPermission` answers the same inside
a team route as anywhere else, and a team permission never answers on the
user at all.

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
renders one row per `SystemPermission::category()`, each with a "select all"
toggle and a checkbox list, and calls `$role->syncPermissions(...)` on save.

Creating a new role (`App\Livewire\Admin\Roles\CreateRole`, route
`roles.create`) uses the same schema, built by the shared
`App\Livewire\Admin\Roles\Concerns\HasPermissionsSchema` trait — this is the
one place that knows how to turn a scope's permission vocabulary into
Filament form components, fill a role's current permissions into
per-category state, and flatten that state back into a single permission
list on save. Both Livewire components use it so the layout/behaviour can't
drift between "create" and "edit".

### Role scopes: one screen, one tab per family of roles

Roles carry a `scope` column (`Role::SYSTEM_SCOPE = 'system'` for the roles
above). The Roles screen doesn't hardcode that: it renders one tab per
`App\Support\Roles\RoleScope` registered with `RoleScopeRegistry` — the same
extension pattern as `NavRegistry` and `PanelRegistry`. A scope declares its
`key()` (the `scope` value), tab `label()`, header `description()`, the
`permissions()` vocabulary its roles can hold (category => [name => label]),
the extra `attributes()` a role created in it needs, and its tab `order()`.

- Core registers `SystemRoleScope` in `App\Support\Roles\AdminRoleScopes`.
- An add-on registers its own from its service provider, e.g. the teams tier's
  `TeamRoleScope` (`TeamPermission` vocabulary). It
  gets a "Team" tab without touching the roles components or views.
- With a single scope registered the screen shows no tabs at all, and its
  URLs stay `/admin/roles` — the default scope never adds a `?scope=`.
- A role whose scope has no registered `RoleScope` (an add-on since removed)
  is a 404 on this screen rather than being edited with the wrong vocabulary.

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
   new one to get a new tab for free), and an `implies()` arm: an action
   carries its area's read floor; a new area's read floor carries
   `ACCESS_ADMIN_PANEL`.
2. Re-run `PermissionSeeder` (`php artisan db:seed --class=PermissionSeeder`,
   or `migrate:fresh --seed` in dev). On an install with roles already
   written, also `php artisan bp:roles:sync-implications` so existing roles
   gain what the new arm implies.
3. Check it somewhere with `Gate::authorize()` / `Gate::allows()` /
   `checkPermissionTo()`, same as any existing case. A new area's pages
   authorize its read floor in `mount()`, never `ACCESS_ADMIN_PANEL`.

No route, form, or Manage Roles change is required — the new permission
appears automatically as a checkbox under its category's tab.
