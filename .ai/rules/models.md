---
paths:
  - 'app/Models/**'
---

# Models

## Use modern Attribute class for accessors, not legacy getXxxAttribute()
New accessors/mutators use `protected function xxx(): Attribute` (e.g. `Attribute::get(...)`), not legacy `getXxxAttribute()`/`setXxxAttribute()` magic methods. `User.php` still has legacy accessors from before this was settled — don't add more, migrate opportunistically.

## `User::canImpersonate()`/`canBeImpersonated()` stay on the model, not UserPolicy
These two are a required contract of `lab404/laravel-impersonate` — its own `ImpersonateController` and Blade directives call these exact method names directly. Don't move their logic into `UserPolicy`; `UserPolicy::impersonate()` delegates to them instead.

## `isSuperAdmin()` reflects a hardcoded boolean column, never a spatie/laravel-permission role
`is_super_admin` is deliberately outside the admin-configurable role system (`spatie/laravel-permission`) so it can never be edited, renamed, or misconfigured via the Roles admin screen. There is no `SystemRole` enum — super admin is the one fixed concept in the app; every other role is admin-defined data.
