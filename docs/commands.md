# Custom Artisan commands

Commands the boilerplate itself adds (as opposed to Laravel/Livewire/Filament's
own built-ins). All boilerplate-provided commands are namespaced under `bp:`
so `php artisan list` groups them separately from framework/package commands —
update this doc whenever one is added, renamed, or removed.

| Command | Purpose |
|---|---|
| `bp:make-admin` | Interactively creates the first super-admin user (first name/last name/email/password prompts via Laravel Prompts). Never a seeder, never hardcoded credentials. |
| `bp:make:panel` | Scaffolds a new admin page panel (a Show card and/or an Edit form section) under `app/Panels/Users/`. Prompts for the panel kind and, for Show panels, the region (main/sidebar). Reminds you to register it in `App\Support\Panels\AdminPanels`, or via `PanelRegistry::for(...)->add(...)` if it's an add-on. See `docs/architecture.md`. |
| `bp:setup` | Shapes a fresh clone into a supported app recipe (see `docs/config-recipes.md`): prompts for the recipe (Public SaaS / Invitation-only / Backoffice / Vanilla) — or takes `--recipe=` — writes the teams config (creation mode, invitation switches, `LOGIN_FALLBACK`, optional label relabelling) to `.env`, and for Vanilla hands off to `bp:remove-teams`. For login-only recipes it prints the two structural edits (registration, public landing) it can't yet auto-apply. Supports `--dry-run` and `--force`. |
| `bp:remove-teams` | Removes the teams tier and makes the boilerplate vanilla — a one-shot setup-time transform. Consumes the `teams:start`/`teams:end` markers to strip the tier's contributions from core files (replacing `User.php`'s block with its `teams:canonical:` line), deletes the teams-coupled tests, runs `composer remove concise-dot-digital/teams` and drops its path repository, and deletes `packages/teams`. Destructive: prints its plan and confirms first; supports `--dry-run` and `--force`. See `docs/teams-distribution.md`. |
