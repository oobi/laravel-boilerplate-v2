---
paths:
  - 'packages/teams/**'
---

# Teams

## Team invitations are two independent switches, decoupled from TeamCreationMode
TeamCreationMode owns only WHO may create a team: self-service | admin-only (config teams.creation). Whether invitations exist is separate — two independent booleans read via Concise\Teams\Support\InvitationPolicy: membersMayInvite() (config teams.invitations.members — owner/admins inviting from the team area) and adminsMayInvite() (teams.invitations.admins — system admins inviting from Admin › Teams). Gate every invitation surface on the matching switch, never on the creation mode: team nav item + ListInvitations route + PendingInvitations member branch use members; admin TeamInvitations route + tab + ShowTeam stat + PendingInvitations admin branch use admins; InviteMember picks the switch by whether the inviter is a system admin. Named modes (SaaS / invitation-only / backoffice) are just recipe presets over these switches — see docs/config-recipes.md — not enum cases.

## Components check one TeamAbility; system-admin authority lives in TeamPolicy::before()
Never write `Gate::allows(SystemPermission::MANAGE_TEAMS) || Gate::allows(TeamAbility::X, $team)` in a component. `TeamPolicy::before()` grants every team ability to a `manage teams` holder (answered at the system scope), then denies non-members, then grants the primary owner the non-delegable acts — so a call site asks for exactly one TeamAbility and the policy is the whole truth. The only direct `manage teams` checks are where the admin/member distinction IS the rule: the admin-only "Add member" action, the self-row exemption in `MembersTable::canActOn()`, and `PendingInvitations::canManage()` (which invitation switch governs the viewer is decided by who they are — `before()` grants `invite` to admins, so the admin switch must be asked first). SystemPermission names are pinned to scope 0 inside team routes by `TeamsServiceProvider::registerSystemPermissionScope()` via `TeamContext::runSystem()`; never call `setPermissionsTeamId()` to work around a scope problem.
