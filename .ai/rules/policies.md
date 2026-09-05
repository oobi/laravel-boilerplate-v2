---
paths:
  - 'app/Policies/**'
---

# Policies

## One Policy per model, per-instance abilities only
Policies cover abilities scoped to a specific model instance (`update`, `delete`, `toggleActive`, ...), auto-discovered by Laravel's model-name convention (`User` -> `UserPolicy`) — no manual registration needed. Global, non-instance abilities stay `SystemPermission` Gates in `AppServiceProvider` (see providers.md).

## Wire Filament Actions with the string form
Use `->authorize('abilityName')` (not a closure) on Filament `Action`/`DeleteAction`/etc. — Filament auto-injects the row's record as the Gate argument, so unauthorized actions are hidden automatically instead of needing a matching manual `->hidden()` check.

## Use `before()` for a blanket super-admin bypass, with explicit per-ability exceptions
Define `before(User $actor, string $ability, mixed $target = null)` to short-circuit every ability for `isSuperAdmin()` actors, rather than repeating `$actor->isSuperAdmin() ||` in each method. List any ability that must still deny the super admin (e.g. self-delete, self-deactivate) or that has its own independent rule set unrelated to the actor's role (e.g. `impersonate`, which is fully owned by `User::canImpersonate()`/`canBeImpersonated()`) as an explicit exclusion that returns `null` to defer to the real method — never assume the blanket bypass is safe for an ability without checking it against every rule that ability encodes. `$target` for an argument-less ability (e.g. `create`) arrives as the model's class-string, not an instance — guard with `instanceof` before comparing IDs.
