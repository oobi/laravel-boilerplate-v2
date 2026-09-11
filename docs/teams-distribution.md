# Teams tier — distribution (remove / eject)

The teams tier ships as an **in-repo path package** (`packages/teams`, namespace
`Concise\Teams`) and is **active by default**. Because it's in your repo, you
already own and can edit it in place — no publishing step needed for ordinary
customisation.

Two structural transforms are supported, both driven by the `teams:` markers in
the core files:

- **Go vanilla** — remove the tier entirely (a project that never wanted teams).
- **Eject** — fold the tier's code into `app/` under the `App\` namespace, for a
  team that wants it app-native rather than as a package.

> Status: today both are **manual procedures** (below). A `bp:` command will
> automate the vanilla path by consuming the same markers; until then, follow
> these steps. Nothing here should be run on a mature app built on teams —
> removing the tier from a developed codebase means redesigning it. These are
> setup-time transforms on a fresh clone.

> Fresh-clone prerequisite (independent of teams): a freshly cloned boilerplate
> needs `composer install` **and** `npm install && npm run build` before it
> boots or its test suite runs — without the Vite manifest, any view-rendering
> request/test throws `Vite manifest not found`. `bp:setup` will fold this in so
> the tree is "ready to roll" after it runs.
>
> Teams-specific: `composer install` (or `composer update`) is also what links
> the `packages/teams` **path package** into `vendor/` (a symlink) and registers
> its autoload. Skip it and nothing under `Concise\Teams\…` resolves — the
> `HasTeams` trait composed into `app/Models/User.php` then throws a fatal
> `Trait "Concise\Teams\Concerns\HasTeams" not found` at boot (and your editor's
> language server flags every teams reference in `User.php`). If you add teams to
> a clone that didn't already have it wired, run
> `composer update concise-dot-digital/teams` to create the symlink and autoload,
> then `php artisan optimize:clear` (and reload your editor's PHP language server
> so it re-indexes `vendor/`).

## The markers

Every place teams touches core is fenced so it's greppable and unambiguous:

```php
// teams:start — why this exists; how to restore it on removal
…code contributed by the teams tier…
// teams:end
```

Find them all:

```bash
grep -rn "teams:start" app database config routes composer.json
```

Some fenced blocks (e.g. the `User` trait composition) carry a **"restore the
canonical line"** note in the opening comment — those are *replace*, not plain
*delete*. Read the fence before removing it.

## Core touch-points (what a transform edits)

| File | What | On removal |
|---|---|---|
| `composer.json` | the `packages/teams` path repository + `require concise-dot-digital/teams` | delete both entries |
| `app/Models/User.php` | `use HasTeams` import + the trait-composition block (`HasTeams insteadof HasRoles`) | **replace** with the canonical trait line quoted in the fence |
| `app/Enums/SystemPermission.php` | the `MANAGE_TEAMS` case, its seed-list entry, its category (3 blocks) | delete each block |
| `database/seeders/DatabaseSeeder.php` | the `TeamRolesSeeder` import + its guarded `$this->call(...)` | delete each block |
| `database/seeders/DemoSeeder.php` | the `TeamSeeder`/`TeamRolesSeeder` imports + their two guarded `$this->call(...)` blocks | delete each block |

The provider is **auto-discovered** (via the package's own composer manifest),
so there is no `bootstrap/providers.php` entry to touch.

## Going vanilla (remove teams)

1. **Composer** — remove the require and the path repository entry, then
   `composer update` (or `composer remove concise-dot-digital/teams` and delete
   the repository block).
2. **Strip the core markers** — all delete-style fences can be stripped with
   `sed -i '' '/teams:start/,/teams:end/d' <file>` — **except `User.php`**, which
   is the one *replace* case: swap its `teams:start…teams:end` block for the
   canonical trait line printed in its fence (deleting it outright would leave
   `User` with no traits). Delete the fenced blocks in `SystemPermission.php`,
   `DatabaseSeeder.php`, and `DemoSeeder.php` — **the fenced blocks only**; those
   seeder files survive and keep their non-teams calls (`PermissionSeeder`,
   `UserSeeder`), which a vanilla app still needs.
3. **Delete the package** — `rm -rf packages/teams`.
4. **Remove the teams tests** — they import `Concise\Teams\…` and would fatal the
   suite otherwise: `rm -rf tests/Feature/Teams`, the `PublicSaas` /
   `InvitationOnly` / `Backoffice` scenario tests, and
   `tests/Feature/Auth/LoginRedirectTest.php` (teams-coupled — `VanillaAppTest`
   covers the core login behaviour). **Keep `VanillaAppTest`** — it's the vanilla
   contract.
5. **`.env`** — no `TEAMS_*` keys are needed; set `LOGIN_FALLBACK=home` and keep
   registration as your app requires. See [config-recipes.md](config-recipes.md)
   (Vanilla).
6. **Migrations** — teams migrations are loaded *from the package*, so once it's
   gone they no longer run on a fresh install. An existing database keeps its
   `teams` / `team_user` / `team_invitations` tables; drop them by hand if you
   want them gone.
7. **Verify** — `composer dump-autoload`, then `php artisan test` (must be green;
   `VanillaAppTest` is the contract) and boot the app. Nothing should reference
   the tier any more: `grep -rn "Concise" app config routes database tests`.
   *(Proven end-to-end in a throwaway clone: 257 tests pass with teams removed.)*

## Ejecting into `app/`

For a project that wants the code app-native. This is a **one-way** transform.

1. **Move the source** — `packages/teams/src` → `app/Teams` (or wherever you
   want it), and rewrite the namespace `Concise\Teams\` → `App\Teams\`
   throughout.
2. **Relocate the rest**:
   - migrations → `database/migrations`
   - views → `resources/views/teams` and update the `teams::` view namespace (or
     re-register it from your provider)
   - config → `config/teams.php`, lang → `lang/vendor/teams`
   - routes → registered from a provider or pulled into `routes/`
3. **Register the provider** — it's no longer auto-discovered, so add the
   renamed provider to `bootstrap/providers.php`.
4. **Composer** — remove the path repository + `require` entry, and add the new
   PSR-4 autoload path if it isn't already covered by `App\`.
5. **Update the core fences** — the `teams:start` blocks reference
   `Concise\Teams\…`; point them at `App\Teams\…`. (They stay fenced — they're
   still teams-contributed, just now app-owned.)
6. **Follow the `// teams:eject:` notes** inside the moved code for any
   namespace/path fix-ups that aren't a straight find-replace.
7. **Verify** — `php artisan test` and boot.

## Why a package, not published-by-default

The teams tier is complex, security-sensitive, ongoing behaviour — the
Jetstream/Fortify shape, not the Breeze "stamp-and-own scaffolding" shape. So it
runs as a package by default and stays coherent and upgradeable, while eject
remains available for teams that prefer full app-native ownership. See the
architecture notes for the fuller rationale.
