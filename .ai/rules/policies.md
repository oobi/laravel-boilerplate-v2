---
paths:
  - 'app/Policies/**'
---

# Policies

## One Policy per model, per-instance abilities only
Policies cover abilities scoped to a specific model instance (`update`, `delete`, `toggleActive`, ...), auto-discovered by Laravel's model-name convention (`User` -> `UserPolicy`) — no manual registration needed. Global, non-instance abilities stay `SystemPermission` Gates in `AppServiceProvider` (see providers.md).

## Wire Filament Actions with the string form
Use `->authorize('abilityName')` (not a closure) on Filament `Action`/`DeleteAction`/etc. — Filament auto-injects the row's record as the Gate argument, so unauthorized actions are hidden automatically instead of needing a matching manual `->hidden()` check.

## The super-admin bypass is a global `Gate::before()`, not a Policy method
It lives in `AppServiceProvider::boot()`, not `UserPolicy::before()` — a Policy's `before()` only intercepts abilities routed through that one Policy, but the bypass must also cover plain `SystemPermission`-named Gate checks (`access admin panel`, etc.) that never go through any Policy at all. List any ability that must still deny the super admin (e.g. self-delete, self-deactivate) or that has its own independent rule set unrelated to the actor's role (e.g. `impersonate`, fully owned by `User::canImpersonate()`/`canBeImpersonated()`) as an explicit exclusion that returns `null` to defer to the real check — never assume the blanket bypass is safe for an ability without checking it against every rule that ability encodes. `$target` for an argument-less ability (e.g. `create`) arrives as the model's class-string, not an instance — guard with `instanceof` before comparing IDs.

## Coarse checks go through spatie/laravel-permission, never a hand-rolled role-name comparison
Use `$actor->hasPermissionTo(SystemPermission::X->value)` for the "does this role have this ability at all" check inside a Policy method — never `$actor->hasRole('some-role-name')` scattered inline, which reintroduces the exact mixed-representation drift this app has deliberately avoided. Role -> permission assignment is admin-configurable (Roles screen); a Policy method should never need to know a role's name, only whether the actor holds a given permission.

## Promote a circumstance-only guard to a named permission once a role needs to differ on it
A hardcoded relationship check (e.g. `$target->isSuperAdmin()` inside `update()`) is correct as long as the answer is the same for every non-super-admin role. The moment a role needs to distinguish itself from that rule (e.g. a role that *can* touch a super admin, or conversely shouldn't be able to do something every other role can), promote it to its own named `SystemPermission` rather than special-casing the role by name.
