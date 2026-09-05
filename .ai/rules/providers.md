---
paths:
  - app/Providers/AppServiceProvider.php
---

# Providers

## Named rate limiters in AppServiceProvider
Define rate limiters via `RateLimiter::for('name', ...)` in `AppServiceProvider::boot()`, referenced by name — not inline `throttle:60,1`.

## Authorization: one global super-admin bypass, spatie/laravel-permission for everything else
A single `Gate::before()` in `AppServiceProvider::boot()` grants a super admin (`$user->isSuperAdmin()` — a hardcoded `is_super_admin` boolean, never a role) every ability app-wide, with named exceptions that return `null` to defer instead of short-circuiting (see policies.md). Every other ability — global (`access admin panel`, `manage system settings`, ...) or per-instance (edit/activate/delete a specific user) — is backed by `spatie/laravel-permission`: `$user->hasPermissionTo(SystemPermission::X->value)` for global checks, `App\Policies\UserPolicy` methods for per-instance ones (still `Gate::authorize('ability', $target)` / Filament's `->authorize('ability')`). `SystemPermission` stays a PHP enum purely as the fixed, code-checked vocabulary and the seed list a database seeder uses to create the matching `Permission` rows — which roles have which permission is admin-configurable via the Roles screen, never a hardcoded `match()`.
