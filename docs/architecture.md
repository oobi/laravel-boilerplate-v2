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
- **Livewire 4** — powers the interactive screens (admin CRUD, auth forms).
  Full-page components live in `app/Livewire/*`.
- **Filament** (`filament/tables` + the forms/infolists/schemas/actions that
  come with it) — used *standalone*, not as a full Filament admin panel.
  Tables power list screens (e.g. `ListUsers`); forms/infolists/schemas power
  create/edit/show screens. Actions (e.g. impersonate, reset password) attach
  to those same Livewire components.
- **lab404/laravel-impersonate** — lets an authorized admin log in as another
  user ("impersonate"), with a banner shown while active.
- **Tailwind CSS 4 + daisyUI 5** — the entire visual design system
  (`resources/css/theme/*`), scaffolded into the app rather than a package so
  it can be freely restyled per project.
- **Alpine.js** — small client-side interactivity, bundled via Livewire.

## In-house (not packages)

- **RBAC** — `SystemRole` / `SystemPermission` enums (`app/Enums/*`) +
  `HasSystemRole` trait on `User`, backing Gate checks throughout the admin
  area. Independent of teams; this is what a single-client project needs on
  its own.
- **Teams** — not built yet. Planned as an opt-in, install-time-only
  additive layer (see `~BOILERPLATE_v2.md` Phase 5): its own models,
  migrations, enums, and screens, authored so core (`User`, RBAC, admin)
  never has to be patched to support it.
- **Admin page composition (panels)** — Show/Edit admin pages are built
  from small, registered "panel" classes rather than one monolithic form/
  infolist, specifically so additive tiers like Teams can add their own
  cards/fields later without patching core files. See `docs/panels.md`.
- **Sidebar navigation** — the app curates its menu declaratively in
  `App\Support\Navigation\AdminNav`; add-ons contribute via
  `NavRegistry::item()`/`group()` rather than editing
  `admin-sidebar-nav.blade.php`. See `docs/navigation.md`.

## Testing & dev tooling

- **PHPUnit 11** (`tests/Feature/*`, `tests/Unit/*`) — all tests are plain
  PHPUnit classes (not Pest).
- **Laravel Pint** — code style; run `vendor/bin/pint --dirty` before
  finalizing changes.
- **Laravel Pail** + **concurrently** — `composer run dev` boots the app
  server, queue listener, log tailer, and Vite together.
- **FakerPHP / Mockery** — test data and mocking.
