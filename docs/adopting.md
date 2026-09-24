# Adopting the boilerplate for a new project

A client project is its own repository that **shares git history** with the
boilerplate. The boilerplate stays a git remote (`upstream`), so fixes flow
between the two as real merges rather than hand-copied diffs.

Don't start from a file copy: without shared history every upstream fix
becomes a manual port.

## 1. Create the project

```bash
git clone git@github.com:oobi/laravel-boilerplate-v2.git client-app
cd client-app
git remote rename origin upstream
git remote add origin git@github.com:<org>/client-app.git
git push -u origin main
```

Then boot it:

```bash
composer run setup          # install, .env, key, migrate, npm build
php artisan bp:setup        # pick a recipe, see docs/config-recipes.md
php artisan bp:make-admin   # first super admin
```

`bp:setup` writes the teams config to `.env`. Choosing the Vanilla recipe
removes the teams tier entirely (`bp:remove-teams`), so only pick it if the
client will never need teams.

Set `APP_NAME`, mail, and database settings in `.env`. Remove
`packages/theme-demo` if the client doesn't want the theme showcase (drop its
path repository and `require` from `composer.json`, then `composer update`).

## 2. Build client features without touching core

Every core file the client edits is a future merge conflict. Extend through
the registries instead, from a client service provider's `boot()`, the same
way `packages/teams` does:

| Adding... | Use | Docs |
|---|---|---|
| Sidebar links | `NavRegistry::group(...)->add(...)` | [navigation.md](navigation.md) |
| User Show/Edit cards | `PanelRegistry::for('users.show')->add(...)` | [panels.md](panels.md) |
| A new family of roles | `RoleScopeRegistry::register(...)` | [permissions.md](permissions.md) |

- Put client domain code in its own folders (`app/Livewire/{Area}/{Domain}`,
  `tests/Feature/{Area}/{Domain}`), following the "group by domain" rule.
- Put client copy in new lang keys rather than rewriting boilerplate strings.
- Put client settings in `.env`, not in edits to boilerplate config defaults.
- When a core edit is unavoidable, keep it small and in its own commit so the
  conflict is easy to resolve later.

## 3. Work on one repo at a time

Run each Claude Code session from the project's own root. Its Boost MCP
server, database, and `.env` belong to that app. The clone already carries
`AGENTS.md`, `.ai/rules/`, `docs/`, and the skills, so it knows the
boilerplate's conventions. When porting a fix, give the session the other
repo with `/add-dir` instead of running one session across both.

## 4. Pull in boilerplate updates

Merge upstream regularly during development so the gap never gets large.
Start from a clean working tree:

```bash
git fetch upstream && git merge --no-ff upstream/main
```

Always use `--no-ff` so each upstream sync is a visible merge commit. After
merging, resolve any conflicts, then run `composer install`, `npm install`,
`php artisan migrate`, and the test suite before pushing.

## 5. Send fixes back to the boilerplate

Anything generic found on the client project belongs upstream.

**Preferred: fix upstream first, then pull down.**

```bash
# in the boilerplate: branch, fix, test, then
git merge --no-ff fix/some-bug

# in the client project
git fetch upstream
git merge --no-ff upstream/main
```

**Fixed in the client first?** Commit the fix on its own, touching only
boilerplate files and containing no client code, then cherry-pick that
commit into the boilerplate:

```bash
# in the boilerplate
git remote add client ../client-app   # once
git fetch client
git cherry-pick <sha>
```

If one commit mixes a generic fix with client work, it can't be cherry-picked
cleanly, so keep them apart from the start.

## 6. Migrations: additive only once a project is live

Before release, the boilerplate edits existing migrations instead of adding
new ones. That stops being safe once a downstream project has a real
database: an edited migration never re-runs where it has already been
applied. From then on, every schema change in the boilerplate goes in a
**new** migration, so `git merge --no-ff upstream/main` followed by
`php artisan migrate` brings the client up to date.

## Checklist

- [ ] Cloned with history, `upstream` remote set
- [ ] `composer run setup`, `bp:setup`, `bp:make-admin` run
- [ ] `.env` configured, theme-demo kept or removed
- [ ] Client features registered from a client service provider
- [ ] Upstream merged regularly with `git merge --no-ff upstream/main`
- [ ] Generic fixes land in the boilerplate and are merged back down
- [ ] Boilerplate schema changes are new migrations from now on
