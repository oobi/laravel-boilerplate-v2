# Permissions

How the app's RBAC vocabulary is defined, how a role gets its permissions,
and how to add a new permission. See `.ai/rules/policies.md` for the
Policy-level conventions this builds on.

## The fixed vocabulary: `App\Enums\SystemPermission`

Every real, code-checked ability in core is a case on the
`SystemPermission` string-backed enum (`app/Enums/SystemPermission.php`).
An add-on brings its own enum and registers it (see "Adding a permission from
an add-on" below), so the full vocabulary is core's cases plus every
registered add-on's, read from `App\Support\Roles\SystemPermissionRegistry`:

```php
enum SystemPermission: string implements SystemPermissionDefinition
{
    case ACCESS_ADMIN_PANEL = 'access admin panel';
    case VIEW_USERS = 'view users';
    case MANAGE_USERS = 'manage users';
    case SUSPEND_USERS = 'suspend users';
    case DELETE_USERS = 'delete users';
    case IMPERSONATE_USERS = 'impersonate users';
    case SET_USER_PASSWORDS = 'set user passwords';
    case MANAGE_ROLES = 'manage roles';

    public function label(): string { /* ... */ }
    public function category(): string { /* ... */ }
    public function implies(): array { /* ... */ }
}
```

The teams tier's area (`view teams`, `manage teams`, `deactivate teams`,
`delete teams`, `manage team ownership`) is its own registered enum,
`Concise\Teams\Enums\TeamSystemPermission`, so a call site names
`TeamSystemPermission::MANAGE_TEAMS` and core never names a teams permission.

### Shape: panel entry, a read floor per area, actions on top

`access admin panel` is **entry only** — the admin shell, the dashboard and
its navigation. It does not open any area's data. Each area has its own read
floor: `view users` opens the user list and profiles read-only, and an add-on's
area brings its own (the teams tier's `view teams`; a future analytics module
would add `view analytics`). The action permissions of an area sit on top of its
floor: `manage users`, `suspend users`, `delete users`, `impersonate users`
each unlock their acts inside the users area.

Why: a role that reaches the panel for one module must not browse another's
data. A teams administrator holds `access admin panel` + `view teams` and never
sees the user roster; a support desk holds `view users` + `manage users` +
`suspend users` and nothing about teams.

**Implications keep that shape honest.** Each permission's `implies()`
declares, one hop at a time, that every action carries its area's read floor
and every read floor carries panel entry. It is applied when a role is
*written* — the Roles form, `SystemRolesSeeder`, `UserFactory::withPermission()`
— through `SystemPermissionRegistry::withImplied()` (transitive, via
`App\Support\Roles\Implications`), so a stored grant is always reachable and
`checkPermissionTo()` and the Gate agree. The form shows the chain: tick
`delete users` and `view users` and `access admin panel` tick and lock with it,
each saying "Included with …" on hover. Never resolve implications at check
time; a check asks for exactly one permission. Any new path that grants
system permissions must call `withImplied()`. Changing the map later is a code
change plus `php artisan bp:roles:sync-implications` with that deploy (see
`docs/boilerplate/commands.md`).

This enum is the single source of truth — permission strings are never
hand-typed at a call site. Each case also declares:

- **`label()`**: the human-readable name shown on the Manage roles screen.
- **`category()`** — which functional-area tab the permission is grouped
  under (e.g. `User management`, `System administration`).
  `SystemPermissionRegistry::byCategory()` groups the whole vocabulary by this
  for the roles form, core's categories first; adding a new category is just
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
for every system permission, core's and every registered add-on's. It's
idempotent, so adding a new case and
re-running the seeder (or `migrate:fresh --seed`) is all that's needed to
make a new permission assignable — nothing else registers permission rows.

```php
foreach (SystemPermissionRegistry::all() as $permission) {
    Permission::findOrCreate($permission->value);
}
```

`tests/TestCase.php` seeds this once per `RefreshDatabase` migration
(`afterRefreshingDatabase()`), so feature tests never need their own
`$this->seed()` call for permissions to exist.

### Default roles: `SystemRolesSeeder`

A fresh install also gets two system roles from
`database/seeders/SystemRolesSeeder.php`: **Administrator** (every system
permission, add-ons' included, including `manage roles` and `set user passwords`; the gap
from a super admin is the one act that isn't a permission, granting super
admin, plus the super admin's reach over anyone, since an Administrator acts
only on users and roles whose permissions it holds) and a limited **Support** (manage and suspend users, impersonate,
listed as the actions alone; the seeder writes them closed over `implies()`,
so the role also holds `view users` and `access admin panel`). They are seeded **only into an empty set of system roles**;
after that the database is the source of truth and renaming, deleting or
replacing them is safe — nothing resurrects them. Edit the seeder's
`defaults()` to change what a fresh install ships; `UserFactory::support()`
reads the same definition so the test fixture can't drift from it. The teams
tier's `TeamRolesSeeder` does the same for team roles.

`bp:make-admin` makes the first account a super admin; `bp:make-admin
--administrator` puts each later admin on the Administrator role instead (see
`docs/boilerplate/commands.md`).

## Checking a permission: `Gate::authorize()` / `checkPermissionTo()`

There's no `Gate::define()` per `SystemPermission` case — spatie/laravel-
permission registers its own `Gate::before()` that resolves any ability name
against the acting user's assigned permissions. So a plain:

```php
Gate::authorize(SystemPermission::ACCESS_ADMIN_PANEL->value);
```

...in a route-group `can:` middleware (see `routes/web.php`'s admin group,
which gates the whole shell on panel entry) or a Livewire component's
`mount()` is enough: no extra registration is required for a
`SystemPermission` case to become checkable.

### Access lost while a page is open

A Livewire page makes more requests after it loads (a filter change, paging,
an action), and Livewire sends them to its own update route, not the page's.
On each one it re-runs only the page route's **persistent middleware**:
`auth`, `can:` (`Illuminate\Auth\Middleware\Authorize`), route model
binding and a few others, plus what the app registers with
`Livewire::addPersistentMiddleware()`. A check in `mount()` runs once, on the
page load. So:

- **An area's read floor goes on its route** as `can:` middleware (the users
  group: `view users`; roles: `manage roles`; the teams admin group:
  `view teams`), as well as in the page's `mount()`. Someone who loses it with
  the page open is refused at their next click, not at their next reload.
- **Our own route middleware is registered as persistent**:
  `DenyLowAssuranceSessions` (AppServiceProvider) and the teams tier's
  `ResolveTeamContext` (membership and an active team, TeamsServiceProvider).
- **A read restriction that can't sit on a route** (one record, or one team
  ability such as a team page's `viewMembers`) goes in the component's
  `booted()`, which Livewire calls
  after `mount()` and on every update. A check in `mount()` alone is the gap
  this section is about.
- **A write check** (one record's ability, such as `UserAbility::UPDATE` on
  Edit user) goes in each action, which re-authorizes when it runs.

Livewire::test() skips all of this. To test it, render the page with
`$this->get()` and send an update with the `livewireUpdate()` helper in
`tests/TestCase.php`, as `LivewireUpdateAuthorizationTest` does.

Inside a Policy checking a role's or user's permission directly, call
**`checkPermissionTo()`**, never `hasPermissionTo()`:
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
abilities (`impersonate`, `assignRole`, and on their own account `delete`/
`toggleActive`/`grantSuperAdmin` and the credential abilities
`updatePasswordDirectly`/`sendPasswordResetLink`/
`resetTwoFactorAuthentication`/`manageTwoFactorGrace`) that must always defer
to their real Policy check instead. The admin area never manages your own
password, two-factor or email: that is the profile, which asks for the
current password.

## Assigning permissions to a role: the Manage roles screen only

Role -> permission assignment is 100% admin-configurable and only happens in
one place: `App\Livewire\Admin\Roles\ManageRoles` (route names `roles.index`
/ `roles.edit`, both render the same component — there's no separate roles
list page, a dropdown at the top switches which role is being edited). It
renders one row per category in `SystemPermissionRegistry::byCategory()`, each with a "select all"
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

Both screens are gated by the **`manage roles`** permission. What stops a
role editor raising their own access is the **coverage rule**
(`App\Support\Roles\Coverage`): you may act on a user or a system role
only if you hold every permission it carries.

- **Roles screen:** a system role is edited or deleted only by someone who
  covers it, covers everyone who holds it (a change reaches them all, so
  nobody takes access away from someone above them), and doesn't hold it; a
  permission you don't hold is shown but can't be ticked, and the save
  re-checks. Each scope also decides who may manage its roles at all
  (`RoleScope::mayManage()`): team roles need `manage teams` as well, since a
  role editor may hold a team role and could otherwise grow it.
- **Users:** every act on another user (edit, suspend, delete, set or reset a
  password, reset 2FA, impersonate, assign roles) needs its permission and
  that you cover them; each role given or taken away must be covered too.
  Nobody assigns their own roles. Peers (the same access) act on each other;
  a super admin untangles. Nobody but a super admin acts on a super admin.
- **Team roles:** the teams tier applies the same rule in team permissions
  (`Concise\Teams\Support\TeamCoverage`) to changing a member's role,
  suspending or removing them, and the role an invitation offers. A system admin
  running teams covers everything.
  Removing a member is its own team permission, `remove team members`, apart
  from `manage team members` (role and suspend): ending a membership is the
  bigger act, so a project can let a role manage people without removing
  them.

Super admin stays a protected login, not a role: only a super admin grants
super admin. Creating users and assigning roles come with `manage users`;
setting a password directly is `set user passwords`.

Assigning a *role* to a *user* is a separate ability (`assignRole`) from
editing the user — see `ShowUser::manageRolesAction()` and
`UserPolicy::assignRole()`'s docblock for why that's never folded into the
generic "update a user" ability.

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
   `checkPermissionTo()`, same as any existing case. A new area's routes
   carry its read floor as `can:` middleware and its pages authorize it in
   `mount()`, never `ACCESS_ADMIN_PANEL` (see "Access lost while a page is
   open" above).

To **remove** a permission nothing checks any more, delete its case (label,
category, `implies()` arm, lang line) and add its value to
`PermissionSeeder::RETIRED`: the next seed deletes the row and so takes it off
every role. Don't seed a permission nothing checks: it shows on the Roles
screen and grants nothing.

No route, form, or Manage roles change is required: the new permission
appears automatically as a checkbox under its category's tab.

## Adding a permission from an add-on

An add-on never edits `SystemPermission`. It declares its own string-backed
enum implementing `App\Support\Roles\SystemPermissionDefinition` (`label()`,
`category()`, `implies()`) and registers it from its service provider's
`boot()`:

```php
SystemPermissionRegistry::register(AuditLogPermission::class);
```

Its cases then behave exactly like core's: they list on the Roles screen
under their own category (after core's), `PermissionSeeder` creates their
rows, the Administrator role in `SystemRolesSeeder` holds them, every write
path closes grants over their `implies()`, and they're checked like any other
permission: `Gate::authorize(AuditLogPermission::VIEW_AUDIT_LOG)`,
`$user->hasSystemPermission(AuditLogPermission::VIEW_AUDIT_LOG)`. An
implication may name a core case: a new area's read floor carries
`SystemPermission::ACCESS_ADMIN_PANEL`. Core code never names an add-on's
permission; the add-on names core's. The teams tier's `TeamSystemPermission`
is the worked example.

A permission name already defined (by core or another add-on) is refused at
registration with a `LogicException`: one spatie row can't have two labels or
two implication lists.

On an install that already has its roles, no role gains the new permissions
automatically. Run `php artisan db:seed --class=PermissionSeeder` to create
their rows, then grant them on the Roles screen. (Until then a check simply
denies, and saving a role with one creates its row.)
