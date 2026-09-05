---
paths:
  - app/Providers/AppServiceProvider.php
---

# Providers

## Named rate limiters in AppServiceProvider
Define rate limiters via `RateLimiter::for('name', ...)` in `AppServiceProvider::boot()`, referenced by name — not inline `throttle:60,1`.

## No Policy classes — Gate::define only
All authorization is `Gate::define()` in `AppServiceProvider` (one gate per `SystemPermission`) — no `app/Policies` classes. Use `Gate::authorize()` to enforce, `Gate::allows()` for boolean/conditional checks.
