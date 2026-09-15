# Teams tier — custom-domain overlay (deployment)

The teams tier serves teams one of two ways, chosen once at setup:

- **Path mode (default).** One host serves everything; a team lives at
  `/{prefix}/{slug}` (e.g. `app.example.com/teams/acme`). Nothing below applies
  — no extra DNS, no wildcard TLS. This is the shape you get out of the box.
- **Host mode (the overlay).** A team *is* a host — its platform subdomain
  `{slug}.{base}` (e.g. `acme.example.com`) or a verified custom domain
  (`acme.com`). The control plane and public face move to their own hosts too.

Enabling the overlay switches **every** team from path URLs to host URLs at once.
It is a **structural, setup-time decision** (wildcard DNS + TLS, cookie scope) —
not a runtime flip on a live path-mode app. Turn it on with the environment
below before a deployment serves traffic.

> Who may manage a team's domains is a separate, runtime question — the
> `MANAGE_DOMAINS` team permission, granted through a role — not configuration.
> See the admin/team **Settings → Domains** section and `~dev/TEAMS_DOMAINS_SCOPE.md §7`.

## The three control hosts (+ team hosts)

In host mode four kinds of host are in play. The first three are yours to
configure; the fourth is every team.

| Host | Serves | Env |
|---|---|---|
| **apex** / `base` | The public landing page (`/`). | `TEAMS_DOMAINS_BASE` |
| **account host** | The account layer everyone needs: auth (`/login`, register, password), profile, the team picker/onboarding, invitation links. Defaults to the apex. | `TEAMS_DOMAINS_ACCOUNT_HOST` (optional) |
| **admin host** | The admin area **only** — dashboard, users, roles, the system "all teams" area, starting impersonation. Nothing else ever binds here, so it can later be fenced to a VPN/IP range. | `TEAMS_DOMAINS_ADMIN_HOST` |
| **team hosts** | A team's members-only pages, at `{slug}.{base}` and any verified custom domain. Resolved from the request host by `TeamHostResolver`. | — (per team, runtime) |

`admin_host` and `base` are **required** when the overlay is on and must be
distinct from each other and from the account host — the app fails loud at boot
(`DomainPolicy::assertConfigured()`) otherwise. Nothing is derived from
`APP_URL`; the hosts are explicit. Fortify's auth host follows the account host
automatically (`config/fortify.php`) — you don't set it twice.

Reserved leftmost labels (`www`, `admin`, `mail`, … — `teams.domains.reserved`)
and whatever the account host resolves to can never be claimed by a team, as a
custom domain's label or a `{slug}.{base}` subdomain.

## Deployment shapes

The overlay sits at the intersection of two independent choices: **who serves
the public face** (Laravel, or a decoupled/headless front-end) and **whether
custom domains are on**.

| Front end | Custom domains | Shape |
|---|---|---|
| Laravel-served | **off** | Single host. Laravel serves the landing, the admin area, and path-based teams (`/teams/{slug}`). The default — none of this file's config needed. |
| Laravel-served | **on** | Laravel serves the public page (apex), the control plane (`admin_host`), **and** team hosts (`{slug}.{base}` + custom). Wildcard DNS + TLS. |
| Decoupled front end | **off** | The front-end owns the public face (`www`/apex); Laravel is admin + API. Teams still path-based on the app host. |
| Decoupled front end | **on** | The front-end owns public; Laravel is `admin_host` (control plane + API) + team hosts. The headless custom-domain SaaS shape — set `account_host` to Laravel's own host so auth/invitations don't land on the front-end's apex. |

The **public-landing toggle is orthogonal**: you can keep or remove Laravel's
landing page independently of any row above (a headless front-end typically
removes it; see `LOGIN_FALLBACK` in `config/fortify.php`).

### `.env` — Laravel-served, custom domains on

```dotenv
TEAMS_DOMAINS_ENABLED=true
TEAMS_DOMAINS_BASE=example.com          # apex + (default) account host; teams at {slug}.example.com
TEAMS_DOMAINS_ADMIN_HOST=admin.example.com
# TEAMS_DOMAINS_ACCOUNT_HOST=...        # optional; unset → the apex (example.com)

SESSION_DOMAIN=.example.com             # REQUIRED — see below
```

### `.env` — headless apex, custom domains on

```dotenv
TEAMS_DOMAINS_ENABLED=true
TEAMS_DOMAINS_BASE=example.com          # front-end owns the apex; teams at {slug}.example.com
TEAMS_DOMAINS_ADMIN_HOST=admin.example.com
TEAMS_DOMAINS_ACCOUNT_HOST=app.example.com   # Laravel serves auth/profile/invitations here

SESSION_DOMAIN=.example.com
```

### DNS / TLS

- `*.{base}` → the app, plus a **wildcard TLS cert** for `*.{base}` — every team
  subdomain terminates there without per-team certs.
- The apex (and `www`) point wherever the public face lives.
- A **verified custom domain** points at the app by the record shown on the
  team's Domains screen; only domains with `verified_at` set ever route.

## `SESSION_DOMAIN` (host-mode requirement)

Auth lives on the account host while teams live on `{slug}.{base}`, so the
session cookie must be shared across them: set `SESSION_DOMAIN=.{base}` (leading
dot). Without it, a login on the account host reads as logged-out on a team
subdomain.

A **verified custom domain on a different registrable domain cannot share this
cookie** — cross-registrable-domain auth (a per-domain login or a token handoff)
is a **documented limitation, deferred**. A team on `acme.com` (not
`acme.example.com`) is reachable, but a session established on `example.com`
won't carry to it.

## Local development (`bp.test`)

With Herd/Valet serving a wildcard `*.bp.test`:

```dotenv
TEAMS_DOMAINS_ENABLED=true
TEAMS_DOMAINS_BASE=bp.test
TEAMS_DOMAINS_ADMIN_HOST=admin.bp.test
SESSION_DOMAIN=.bp.test
```

The public landing is `bp.test`, the admin area `admin.bp.test`, and a team
`acme` is `acme.bp.test`. Direct navigation to a team host you don't belong to
returns 403 — that's the members-only design, not a misconfiguration; enter such
a team from the admin **Teams → Overview** screen (which offers to impersonate
the owner if you may).

## Removing or ejecting the overlay

The overlay ships behind `teams.domains.enabled` and leaves path mode untouched,
so "remove custom domains" is just `TEAMS_DOMAINS_ENABLED=false`. Removing or
ejecting the **teams tier as a whole** follows the `// teams:start/end` and
`// teams:eject:` markers — see [teams-distribution.md](teams-distribution.md).
