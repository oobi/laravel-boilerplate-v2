# Teams tier — host routing (subdomains & custom domains)

The teams tier serves teams one of two ways, chosen once at setup:

- **Path mode (default).** One host serves everything; a team lives at
  `/{prefix}/{slug}` (e.g. `app.example.com/teams/acme`). Nothing below applies
  — no extra DNS, no wildcard TLS. This is the shape you get out of the box.
- **Host mode (the overlay).** A team *is* a host — its platform subdomain
  `{slug}.{base}` (e.g. `acme.example.com`). The control plane and public face
  move to their own hosts too. Optionally, a team may also route on its own
  **verified custom domain** (`acme.com`) — but see the status note below.

Enabling the overlay switches **every** team from path URLs to host URLs at once.
It is a **structural, setup-time decision** (wildcard DNS + TLS, cookie scope) —
not a runtime flip on a live path-mode app. Turn it on with the environment
below before a deployment serves traffic.

## Two switches: subdomains vs custom domains

Host routing has two tiers, and they are separate config flags:

| Flag (env) | Default | What it turns on |
|---|---|---|
| `teams.domains.enabled` (`TEAMS_DOMAINS_ENABLED`) | off | **Host mode.** Every team is reached at `{slug}.{base}`. **Complete and supported.** |
| `teams.domains.custom_domains` (`TEAMS_DOMAINS_CUSTOM`) | off | **Custom domains.** A team may additionally route on its own verified domain. Only has effect when `enabled` is also on. **Incomplete — see below.** |

`DomainPolicy::customDomainsEnabled()` is true only when *both* are on. With
custom domains off, the `Domain` model, its verification, and the Domains
tab/UI are dormant, and only `{slug}.{base}` resolves.

> ### ⚠️ Custom domains are an incomplete placeholder
>
> **Subdomain mode (`{slug}.{base}`) is production-ready. Custom domains are
> not.** A team reached on its *own registrable domain* (`acme.com`, not
> `acme.example.com`) **cannot hold a session today**: the `.{base}` session
> cookie can't be sent to a different registrable domain, so a member visiting
> `acme.com` is treated as a guest, is sent to the account host to sign in, is
> redirected back, and is a guest again — an endless login loop (logout has the
> same problem). Verification, storage, routing and the management UI all exist,
> but **without the session handoff a foreign custom domain is unusable.**
>
> Leave `TEAMS_DOMAINS_CUSTOM` **off** unless every team lives on the *same*
> registrable domain as `base` (where the shared cookie already covers them). The
> Domains screen carries this same warning so no one enables it by accident.
> **What it would take to finish it is spelled out in
> [Completing custom domains](#completing-custom-domains) below.**

> Who may manage a team's domains is a separate, runtime question — the
> `MANAGE_DOMAINS` team permission, granted through a role — not configuration.
> It (and the Domains tab) only appears while the custom-domains tier is on. See
> the team/admin **Domains** tab and `.ai/rules/teams.md`.

## The three control hosts (+ team hosts)

In host mode four kinds of host are in play. The first three are yours to
configure; the fourth is every team.

| Host | Serves | Env |
|---|---|---|
| **apex** / `base` | The public landing page (`/`). | `TEAMS_DOMAINS_BASE` |
| **account host** | The account layer everyone needs: auth (`/login`, register, password), profile, the team picker/onboarding, invitation links. Defaults to the apex. | `TEAMS_DOMAINS_ACCOUNT_HOST` (optional) |
| **admin host** | The admin area **only** — dashboard, users, roles, the system "all teams" area, starting impersonation. Nothing else ever binds here, so it can later be fenced to a VPN/IP range. | `TEAMS_DOMAINS_ADMIN_HOST` |
| **team hosts** | A team's members-only pages, at `{slug}.{base}` (always) and — when the custom-domains tier is on — any verified custom domain. Resolved from the request host by `TeamHostResolver`. | — (per team, runtime) |

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
the public face** (Laravel, or a decoupled/headless front-end) and **whether the
custom-domains tier is on** (subject to the status note above).

| Front end | Custom domains | Shape |
|---|---|---|
| Laravel-served | **off** | Laravel serves the landing, the admin area, and teams at `{slug}.{base}` (host mode) or `/teams/{slug}` (path mode). The common shape. |
| Laravel-served | **on** | As above, plus team hosts on verified custom domains. Only sound when those domains share `base`'s registrable domain (else see the session limitation). Wildcard DNS + TLS. |
| Decoupled front end | **off** | The front-end owns the public face (`www`/apex); Laravel is `admin_host` (control plane + API) + team subdomains. Set `account_host` to Laravel's own host so auth/invitations don't land on the front-end's apex. |
| Decoupled front end | **on** | The headless custom-domain SaaS shape — the one that most wants custom domains, and the one the session handoff is a prerequisite for. |

The **public-landing toggle is orthogonal**: you can keep or remove Laravel's
landing page independently of any row above (a headless front-end typically
removes it; see `LOGIN_FALLBACK` in `config/fortify.php`).

### `.env` — Laravel-served, subdomains only (recommended)

```dotenv
TEAMS_DOMAINS_ENABLED=true
TEAMS_DOMAINS_BASE=example.com          # apex + (default) account host; teams at {slug}.example.com
TEAMS_DOMAINS_ADMIN_HOST=admin.example.com
# TEAMS_DOMAINS_ACCOUNT_HOST=...        # optional; unset → the apex (example.com)
# TEAMS_DOMAINS_CUSTOM=false            # leave off — custom domains are incomplete (see the doc)

SESSION_DOMAIN=.example.com             # REQUIRED — see below
```

### `.env` — headless apex

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
  team's Domains screen; only domains with `verified_at` set ever route (and only
  while the custom-domains tier is on).

## `SESSION_DOMAIN` (host-mode requirement)

Auth lives on the account host while teams live on `{slug}.{base}`, so the
session cookie must be shared across them: set `SESSION_DOMAIN=.{base}` (leading
dot). Without it, a login on the account host reads as logged-out on a team
subdomain. This covers the apex, the admin host, and every `{slug}.{base}`
subdomain — the whole registrable domain.

It does **not** cover a custom domain on a *different* registrable domain — which
is the crux of why custom domains are unfinished.

## Completing custom domains

To make a team usable on its own registrable domain, a **cross-domain session
handoff** has to be built (a deferred item). Because it extends the
authentication boundary onto tenant-controlled
domains, it must be built to the security bar below or not at all — a half-built
version is worse than none, since every project inherits it:

1. **The handoff itself.** Authenticate on the account host as normal, then
   redirect to the custom domain with a **single-use, short-TTL, signed** token;
   the destination verifies it and establishes a session there.
2. **Replay protection.** The token must be consumed server-side (one use) and
   expire quickly — a replayable token is a session-hijack primitive.
3. **No token leakage.** A token in a URL leaks via `Referer`, history and logs;
   exchange it and strip it from the URL immediately, and set `Referrer-Policy`.
4. **Session fixation.** Regenerate the session on the destination domain after
   the handoff.
5. **Audience binding.** Bind the token to the specific user *and* the specific
   verified domain, so it can't be replayed against another host.
6. **Destination validation.** Only ever hand off to a domain that is *currently
   verified for that team* — never an arbitrary host (open-redirect + token
   exfiltration otherwise).
7. **Re-validation, not verify-once.** Today a verified domain that later lapses
   and is re-registered by someone else only renders login loops (harmless). Once
   a domain can mint sessions, a stale `verified_at` becomes a way onto a host an
   attacker now controls — so verification must be periodically re-checked.
8. **Logout parity.** The logout POST has the same cross-domain cookie problem;
   it needs the equivalent handling.

Until this exists, custom domains stay opt-in (`TEAMS_DOMAINS_CUSTOM`, default
off) and clearly flagged in the UI.

## Local development (`bp.test`)

With Herd/Valet serving a wildcard `*.bp.test`:

```dotenv
TEAMS_DOMAINS_ENABLED=true
TEAMS_DOMAINS_BASE=bp.test
TEAMS_DOMAINS_ADMIN_HOST=admin.bp.test
SESSION_DOMAIN=.bp.test
# TEAMS_DOMAINS_CUSTOM=true            # only to exercise the (incomplete) custom-domain UI
```

The public landing is `bp.test`, the admin area `admin.bp.test`, and a team
`acme` is `acme.bp.test`. Direct navigation to a team host you don't belong to
returns 403 — that's the members-only design, not a misconfiguration; enter such
a team from the admin **Teams → Overview** screen (which offers to impersonate
the owner if you may).

## Removing or ejecting the overlay

The overlay ships behind `teams.domains.enabled` and leaves path mode untouched,
so "go back to path routing" is just `TEAMS_DOMAINS_ENABLED=false`, and "drop
custom domains" is `TEAMS_DOMAINS_CUSTOM=false`. Removing or ejecting the **teams
tier as a whole** follows the `// teams:start/end` and `// teams:eject:` markers
— see [teams-distribution.md](teams-distribution.md).
