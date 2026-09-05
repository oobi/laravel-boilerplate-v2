---
paths:
  - app/Providers/AppServiceProvider.php
---

# Providers

## Named rate limiters in AppServiceProvider
Define rate limiters via `RateLimiter::for('name', ...)` in `AppServiceProvider::boot()`, referenced by name — not inline `throttle:60,1`.

## Authorization: Gate::define for global abilities, Policies for per-instance ones
`Gate::define()` in `AppServiceProvider` (one gate per `SystemPermission`) covers global, non-instance abilities (`access admin panel`, `manage system settings`, `view system analytics`, and a narrow `manage users` used only by the users "empty trash" bulk action). Anything scoped to a specific model instance (edit/activate/delete a specific user, etc.) is a real Policy — see `app/Policies/UserPolicy.php` — invoked via `Gate::authorize('ability', $target)` / `Gate::allows('ability', $target)` or Filament's `->authorize('ability')` on Actions.
