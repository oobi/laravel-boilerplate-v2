---
paths:
  - 'packages/teams/**'
---

# Teams

## Team invitations are two independent switches, decoupled from TeamCreationMode
TeamCreationMode owns only WHO may create a team: self-service | admin-only (config teams.creation). Whether invitations exist is separate — two independent booleans read via Concise\Teams\Support\InvitationPolicy: membersMayInvite() (config teams.invitations.members — owner/admins inviting from the team area) and adminsMayInvite() (teams.invitations.admins — system admins inviting from Admin › Teams). Gate every invitation surface on the matching switch, never on the creation mode: team nav item + ListInvitations route + PendingInvitations member branch use members; admin TeamInvitations route + tab + ShowTeam stat + PendingInvitations admin branch use admins; InviteMember picks the switch by whether the inviter is a system admin. Named modes (SaaS / invitation-only / backoffice) are just recipe presets over these switches — see docs/config-recipes.md — not enum cases.
