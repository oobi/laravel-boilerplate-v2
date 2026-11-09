---
paths:
  - routes/web.php
---

# Routes

## Profile routes are not admin-gated
`profile.edit` / `profile.password` / `profile.two-factor` sit at `/profile`, never inside the `can:ACCESS_ADMIN_PANEL` admin group: a profile is not an admin page, and every signed-in user (admin or not) must reach it, except impersonated sessions, which DenyLowAssuranceSessions refuses (see profile.md). `profile.edit` is `auth` only, so an unverified user can fix a mistyped address (#46; the email change needs the current password, and photos wait for verification); `profile.password` and `profile.two-factor` are `auth`+`verified`. In the teams tier's host mode this group binds to `$accountHost` (`config('teams.domains.account_host')`, defaulting to the apex/base), never `$adminHost` (see `docs/boilerplate/teams-domains.md`). `admin_host` in host mode is quarantined to admin-only pages (dashboard/users/roles/system-teams/impersonation) precisely so it stays safe to fence to a VPN/IP range later; never add a non-admin route to that group. The profile pages render in `layouts.account` (via `#[Layout('layouts.account')]` on the Livewire component), never by binding the route to an area: that layout renders the shell of the area the user was last in (AccountAreaRegistry), else the sidebar-less shell with `:home` resolved by `App\Support\Auth\Destination::home()`.

## Admin route groups remember the admin area; add-on areas register a resolver
Account pages (profile, password, two-factor, and any future page on layouts.account) render inside the shell of the area the user was last in, via App\Support\AccountArea\AccountAreaRegistry. Every route behind `can:access admin panel` must also carry `RememberAccountArea::class.':admin'`, listed after the group's `can:access admin panel` so only a user who can enter the admin area is recorded; ProfileAreaShellTest fails on an admin route without it. An add-on area calls AccountAreaRegistry::remember() where its area is entered (teams: ResolveTeamContext) and registers a resolver from its provider that re-checks access and returns an AccountShell, or null. Hand the layout its data (the teams tier passes the team); never set TeamContext on an account page, since that also scopes storage, cache and jobs.

## Numeric-id route parameters get the global bigint pattern
A route parameter that binds a model by its bigint id (`{user}`, `{role}`, `{entry}`, Filament's `{export}`/`{import}`) is constrained globally to `[0-9]{1,18}` in `AppServiceProvider::constrainRouteIds()`, so a junk id is a 404 before any query: Postgres errors on a value it can't cast (GitHub oobi#89). Add a new numeric-id parameter name there, not a per-route `->where()`. It runs in `register()`, not `boot()`: a pattern only reaches routes created after it, and Filament creates routes while booting. Add-ons set their own (`invitation` in packages/teams/routes/teams.php). Prove it with a 404 test run on Postgres (DB_CONNECTION=pgsql); SQLite returns 404 either way.
