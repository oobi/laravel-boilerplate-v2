---
paths:
  - 'app/**'
---

# App

## Prefer collect() chains over array_map/foreach
Use fluent `collect()->map()->filter()...` chains for collection transformations instead of `array_map`/`array_filter` or manual `foreach`.

## Email addresses are normalized at the User model, and uniqueness is validated against the normalized value
`User::email` has an `Attribute::set` mutator that trims and lowercases, so every write path (forms, Fortify actions, `bp:make-admin`, seeders, factories) stores a canonical address. This is not cosmetic: `fortify.lowercase_usernames` is on, so a mixed-case stored email can never be logged in with on a case-sensitive connection.

Any new form or action that accepts an email must therefore validate uniqueness against `User::normalizeEmail($value)`, not the raw input — otherwise a case-variant of a taken address passes validation and then hits the `users.email` unique index as a QueryException. In Filament schemas do this with `->mutateStateForValidationUsing(fn (?string $state): ?string => User::normalizeEmail($state))` before `->unique(...)`; in plain `Validator::make()` paths normalize `$input['email']` first.

`users` uses soft deletes, and Laravel's `unique` rule is not soft-delete aware — that is correct here, since a trashed user still holds the row and the DB index. Don't add `withoutTrashed()` to these rules.

## Check a SystemPermission on a User with hasSystemPermission(), not checkPermissionTo()
`$user->hasSystemPermission(SystemPermission::X)` asks through the Gate, so every hook that qualifies the answer applies (the super-admin bypass). System roles are stock spatie with its teams feature off; a team role is never a spatie assignment on the user. `checkPermissionTo()` is still right inside a Policy method that is already being evaluated by the Gate (UserPolicy) and inside `Team::memberHasPermission()` (deliberately team-scoped); anywhere else on a User — model helpers like `canAccessAdmin()`, actions, components — use `hasSystemPermission()`. Never `hasPermissionTo()`: it throws for an unseeded permission.

## `access admin panel` is entry only; each admin area gates on its own read floor, and actions imply it
`access admin panel` opens the shell, dashboard and nav — nothing else. An admin area's pages authorize its READ floor in `mount()` (`view users` for ListUsers/ShowUser, an add-on's `view teams` for its pages, `view system analytics` for analytics), never `ACCESS_ADMIN_PANEL`; the action permissions (`manage/suspend/delete/impersonate users`) sit on top and gate their own surfaces. Why: a stats-only admin must reach the panel without browsing the roster. `SystemPermission::implies()` declares one hop per case (action → area view, view → panel entry) and every write path stores the transitive closure via `SystemPermission::withImplied()` (Roles form, SystemRolesSeeder, UserFactory::withPermission) so a stored grant is always reachable — never resolve implications at check time; a check asks for exactly one permission. A new area = a new `view x` case with an `implies()` arm to ACCESS_ADMIN_PANEL, its pages mounting on it, and its actions implying it. Changing the map later = `php artisan bp:roles:sync-implications` with that deploy. Dashboard content that belongs to an area (the recent-users roster) renders only behind that area's read floor.
