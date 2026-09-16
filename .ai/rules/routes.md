---
paths:
  - routes/web.php
---

# Routes

## Profile routes are not admin-gated
`profile.edit` / `profile.password` / `profile.two-factor` sit in their own `auth`+`verified` group at `/profile`, never inside the `can:ACCESS_ADMIN_PANEL` admin group — a profile is not an admin page, and every signed-in verified user (admin or not) must reach it. In the teams tier's host mode this group binds to `$accountHost` (`config('teams.domains.account_host')`, defaulting to the apex/base), never `$adminHost` — see `docs/teams-domains.md`. `admin_host` in host mode is quarantined to admin-only pages (dashboard/users/roles/system-teams/impersonation) precisely so it stays safe to fence to a VPN/IP range later; never add a non-admin route to that group. The profile pages render in the sidebar-less `layouts.account` (via `#[Layout('layouts.account')]` on the Livewire component), not `layouts.admin`, with `:home` resolved by `App\Support\Auth\Destination::home()`.
