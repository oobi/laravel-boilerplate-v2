# Laravel admin boilerplate

A Laravel 12 starting point for staff back-office apps: Livewire 4, Filament 5
tables and forms, Tailwind 4 with daisyUI, Fortify authentication (two-factor
included), spatie roles and permissions, and an optional teams tier
(`packages/teams`). Every project starts from it, so it aims to be secure,
accessible and consistent out of the box.

## Start a project

A project is its own repository that shares git history with this one, so
fixes can flow both ways. See [docs/adopting.md](docs/adopting.md), then:

```bash
composer run setup          # install, .env, key, migrate, npm build
php artisan bp:setup        # pick a recipe (docs/config-recipes.md)
php artisan db:seed         # permissions and default roles, named by the recipe
php artisan bp:make-admin   # the first super admin
composer run dev            # server, queue, logs and Vite together
```

Requires PHP 8.4, Composer, Node, and MySQL (tests run on in-memory SQLite).

## Tests

```bash
php artisan test --compact tests/Feature/Some/FileTest.php   # while working
composer test:fast          # the parallel suite minus the slow group (pre-commit)
php artisan test --parallel --compact                         # before merging
```

## Docs

| Topic | Doc |
|---|---|
| Stack and in-house pieces | [architecture.md](docs/architecture.md) |
| Starting a project, taking upstream fixes | [adopting.md](docs/adopting.md) |
| Recipes (SaaS, invitation-only, back office, vanilla) | [config-recipes.md](docs/config-recipes.md) |
| Roles and permissions | [permissions.md](docs/permissions.md) |
| Login, passwords, two-factor, impersonation | [authentication.md](docs/authentication.md) |
| Sidebar navigation | [navigation.md](docs/navigation.md) |
| Show and Edit page panels | [panels.md](docs/panels.md) |
| Components and styling | [design-system.md](docs/design-system.md) |
| `bp:` commands | [commands.md](docs/commands.md) |
| Removing or ejecting the teams tier | [teams-distribution.md](docs/teams-distribution.md) |
| Team subdomains and custom domains | [teams-domains.md](docs/teams-domains.md) |

Guidance for coding agents lives in [AGENTS.md](AGENTS.md),
[.github/copilot-instructions.md](.github/copilot-instructions.md) and the
path-scoped rules in [.ai/rules](.ai/rules/index.md).
