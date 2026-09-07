# Architecture overview

A quick orientation for a new developer — what's installed and what it's for.
Not exhaustive; see `~BOILERPLATE_v2.md` for the full rationale/history behind
these choices.

## Core stack

- **Laravel 12** (PHP ^8.4) — the framework. Everything lives directly in this
  app; there are no first-party Composer packages for core features.
- **Laravel Fortify** — headless auth backend (login, registration, password
  reset, email verification, two-factor auth). It ships no UI; all views under
  `resources/views/auth/*` are our own daisyUI-styled Blade, not Jetstream's.
  See `docs/authentication.md` for how the password-change paths and account
  inactivation (session invalidation) work.
- **Livewire 4** — powers the interactive screens (admin CRUD, auth forms).
  Full-page components live in `app/Livewire/*`, grouped by domain once an
  area has more than one component (e.g. `app/Livewire/Admin/Users/*`,
  mirrored by `resources/views/livewire/admin/users/*` and
  `tests/Feature/Admin/Users/*`) — see "Group by domain, not by type" in
  `.github/copilot-instructions.md`.
- **Filament** (`filament/tables` + the forms/infolists/schemas/actions that
  come with it) — used *standalone*, not as a full Filament admin panel.
  Tables power list screens (e.g. `ListUsers`); forms/infolists/schemas power
  create/edit/show screens. Actions (e.g. impersonate, reset password) attach
  to those same Livewire components.
- **lab404/laravel-impersonate** — lets an authorized admin log in as another
  user ("impersonate"), with a banner shown while active.
- **spatie/laravel-permission** — admin-configurable roles and permissions.
  Super admin is the one exception: a hardcoded `is_super_admin` boolean, not
  a package role, so it can never be edited via the Roles admin screen. See
  `.ai/rules/policies.md`.
- **Tailwind CSS 4 + daisyUI 5** — the entire visual design system
  (`resources/css/theme/*`), scaffolded into the app rather than a package so
  it can be freely restyled per project.
- **Alpine.js** — small client-side interactivity, bundled via Livewire.

## In-house (not packages)

- **RBAC** — the `is_super_admin` flag + `HasSystemRole`-equivalent trait on
  `User`, plus `App\Policies\UserPolicy` for per-instance abilities
  (edit/activate/delete a specific user). Role and permission storage itself
  is spatie/laravel-permission (above); this in-house layer is only the
  super-admin bypass and the relationship-aware Policy rules (self-checks,
  "can't touch a super admin") that a permission table alone can't express.
- **Teams** — not built yet. Planned as an opt-in, install-time-only
  additive layer (see `~BOILERPLATE_v2.md` Phase 5): its own models,
  migrations, and screens, authored so core (`User`, RBAC, admin) never has
  to be patched to support it. Team-scoped roles reuse spatie/laravel-permission's
  own teams feature rather than a second bespoke role system.
- **Admin page composition (panels)** — Show/Edit admin pages are built
  from small, registered "panel" classes rather than one monolithic form/
  infolist, specifically so additive tiers like Teams can add their own
  cards/fields later without patching core files. See `docs/panels.md`.
- **Sidebar navigation** — the app curates its menu declaratively in
  `App\Support\Navigation\AdminNav`; add-ons contribute via
  `NavRegistry::item()`/`group()` rather than editing
  `admin-sidebar-nav.blade.php`. See `docs/navigation.md`.

## Scope: staff back-office, not a customer frontend

This is an **admin/back-office** boilerplate. The people who authenticate here
are staff, so self-service account management (profile, password, two-factor)
lives **inside the admin shell** (`layouts.admin`, under the `/admin` gate) — a
user without `ACCESS_ADMIN_PANEL` gets a 403, not a half-styled public page.

A **customer-facing frontend is deliberately out of scope**: a real app's public
UI is unpredictable (Blade, Livewire, Inertia, or a headless Nuxt/SPA), so the
boilerplate ships the reusable *capability* rather than a throwaway view. Account
logic is already presentation-agnostic — the Fortify actions in
`app/Actions/Fortify`, the `App\Livewire\Concerns\ConfirmsPassword` trait, and
Fortify's headless endpoints. A team building customer account screens (in any
stack) reuses those; whether "users without a system role" are customers or
something else is their business logic to decide, not the boilerplate's.

## Testing & dev tooling

- **PHPUnit 11** (`tests/Feature/*`, `tests/Unit/*`) — all tests are plain
  PHPUnit classes (not Pest).
- **Laravel Pint** — code style; run `vendor/bin/pint --dirty` before
  finalizing changes.
- **Laravel Pail** + **concurrently** — `composer run dev` boots the app
  server, queue listener, log tailer, and Vite together.
- **FakerPHP / Mockery** — test data and mocking.
