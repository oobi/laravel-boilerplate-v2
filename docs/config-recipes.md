# Config recipes

Common application shapes and the settings that produce them. Each recipe is
just a combination of switches — none of them is a mode that silently changes
the others, so mix them freely. Runtime settings live in `.env`; the two
*structural* choices (public landing page, public registration) are one-time
scaffolding, handled by the setup recipe rather than a runtime flag.

## The settings

| Setting | Where | Why it matters |
|---|---|---|
| `TEAMS_CREATION` | `.env` | Who may create a team: `self-service` (any user) or `admin-only` (admins, via Admin › Teams). |
| `TEAMS_MEMBER_INVITATIONS` | `.env` | Whether a team's owner/admins can invite people by email from the team area. |
| `TEAMS_ADMIN_INVITATIONS` | `.env` | Whether a system admin can invite people by email from Admin › Teams. |
| `LOGIN_FALLBACK` | `.env` | Where a user with no system role and no team lands: `home` (public page) or `reject` (logged back out — no public surface). |
| Public registration | scaffold | Fortify's `registration` feature — open self-signup, or accounts created only by admins. |
| Public landing page | scaffold | Whether `/` is a public page or the app is login-only. |

Default destinations (not configurable, but not forced either): on a bare login,
a user with a system role lands on the admin dashboard and a non-admin with (or
awaiting) a team lands in the team area. These are only the *defaults* — if the
user was deep-linked somewhere and bounced to login, that intended URL is
honoured instead (a system user who clicked a `/teams` link is taken there, not
to the dashboard). Only the "neither" tail above is a configurable choice.

## Recipes

### Public SaaS (the default)

Anyone signs up, creates their own team, and invites their staff.

| Setting | Value |
|---|---|
| `TEAMS_CREATION` | `self-service` |
| `TEAMS_MEMBER_INVITATIONS` | `true` |
| `TEAMS_ADMIN_INVITATIONS` | `true` |
| `LOGIN_FALLBACK` | `home` |
| Public registration | on |
| Public landing page | keep |

*Why:* self-service creation and member invitations are the growth loop; a
signed-up user with no team yet lands on onboarding to create one.

### Invitation-only SaaS

Admins seed the teams; existing members grow them by invitation. No open
self-service team creation.

| Setting | Value |
|---|---|
| `TEAMS_CREATION` | `admin-only` |
| `TEAMS_MEMBER_INVITATIONS` | `true` |
| `TEAMS_ADMIN_INVITATIONS` | `true` |
| `LOGIN_FALLBACK` | `home` |
| Public registration | usually on (invitees register through the invite link either way) |
| Public landing page | keep |

*Why:* `admin-only` stops users spinning up their own teams, while member
invitations still let a team owner bring colleagues in.

### Backoffice / administration platform

A purely internal tool (e.g. managing a chain of salons). Everything is behind
a login; admins create the accounts and the teams and add staff directly.

| Setting | Value |
|---|---|
| `TEAMS_CREATION` | `admin-only` |
| `TEAMS_MEMBER_INVITATIONS` | `false` |
| `TEAMS_ADMIN_INVITATIONS` | `false` |
| `LOGIN_FALLBACK` | `reject` |
| Public registration | off |
| Public landing page | remove (login-only) |

*Why:* both invitation switches off removes every invite surface — admins add
existing accounts to teams instead; `reject` + no public page means an
unprovisioned login is turned away with a "contact an admin" notice rather than
dumped on a page that isn't there. (If the teams tier is installed, an
unprovisioned user instead lands on its "ask an admin" onboarding, so `reject`
mainly matters for a teams-less build.)

## Secondary knobs

Independent of the recipes above, tune per project:

| Setting | Why |
|---|---|
| `TEAMS_LABEL_SINGULAR` / `TEAMS_LABEL_PLURAL` | Rename "team" throughout (e.g. "Salon" / "Salons") without touching code. |
| `TEAMS_MAX_PER_USER` | Cap how many teams one user may own under self-service (unset = unlimited). |
| `TEAMS_MULTIPLE_ROLES` | Allow a member several team roles at once, or exactly one ("a position"). |
