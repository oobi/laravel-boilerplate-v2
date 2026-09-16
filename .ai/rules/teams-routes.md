---
paths:
  - packages/teams/routes/teams.php
---

# Teams Routes

## Host mode has three hosts: apex, account host, admin host
`DomainPolicy::adminHost()` (admin_host) is admin-only: dashboard/users/roles/system `teams.*`/impersonation, gated `ACCESS_ADMIN_PANEL`. `DomainPolicy::accountHost()` (account_host, defaults to `base()`/the apex when unset) carries everything else a signed-in-or-signing-in user needs: Fortify auth (`config/fortify.php`'s `domain`), `/profile*`, the team front door/picker/onboarding (`team.index`/`select`/`onboarding`), and invitation accept/register links — all keep the `/teams` prefix in both path and host mode now (no bare `/select`). Never bind a non-admin route to `adminHost()`, and never bind login/profile/invitations there — that's what makes admin_host safe to fence to a VPN/IP range later. `DomainPolicy::controlPlaneHost()` no longer exists (removed 5h.7); use `accountHost()` or `adminHost()` explicitly. A team's own picking logic (current team → only team → picker → onboarding) lives in `Concise\Teams\Support\TeamDestination::resolve()`, shared by `TeamRedirect` and the post-login resolver so login lands on the team in one hop. See `docs/teams-domains.md`.
