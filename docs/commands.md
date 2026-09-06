# Custom Artisan commands

Commands the boilerplate itself adds (as opposed to Laravel/Livewire/Filament's
own built-ins). All boilerplate-provided commands are namespaced under `bp:`
so `php artisan list` groups them separately from framework/package commands —
update this doc whenever one is added, renamed, or removed.

| Command | Purpose |
|---|---|
| `bp:make-admin` | Interactively creates the first super-admin user (first name/last name/email/password prompts via Laravel Prompts). Never a seeder, never hardcoded credentials. |
| `bp:make:panel` | Scaffolds a new admin page panel (a Show card and/or an Edit form section) under `app/Panels/Users/`. Prompts for the panel kind and, for Show panels, the region (main/sidebar). Reminds you to register it in `App\Support\Panels\AdminPanels`, or via `PanelRegistry::for(...)->add(...)` if it's an add-on. See `docs/architecture.md`. |
