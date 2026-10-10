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

> Status: going vanilla is automated by **`php artisan bp:remove-teams`**, which
> consumes the markers below; ejecting is still a manual procedure. Nothing here
> should be run on a mature app built on teams: removing the tier from a
> developed codebase means redesigning it. These are setup-time transforms on a
> fresh clone.

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
| `app/Enums/SystemPermission.php` | the five team permission cases (`view teams`, `manage teams`, `deactivate teams`, `delete teams`, `manage team ownership`), their `label()` arms, their `category()` arm and their `implies()` arms (4 fenced blocks). The arms call `team_trans()`, a package helper — deliberate, so the labels relabel with the tier; the fence removes the dependency with the tier | delete each block |
| `database/seeders/DatabaseSeeder.php` | the `TeamRolesSeeder` import + its guarded `$this->call(...)` | delete each block |
| `database/seeders/DemoSeeder.php` | the `TeamSeeder`/`TeamRolesSeeder` imports + their two guarded `$this->call(...)` blocks | delete each block |
| `config/fortify.php` | the auth domain for the custom-domain overlay's host mode (auth on the account host) | delete the block |
| `routes/web.php` | the host-mode host and path variables (`$apexHost`, `$accountHost`, `$adminHost`, `$adminPath`) | delete the block, then point the route groups that use them back at plain paths (see #22) |

The provider is **auto-discovered** (via the package's own composer manifest),
so there is no `bootstrap/providers.php` entry to touch.

## Going vanilla (remove teams)

Run `php artisan bp:remove-teams --dry-run` to see the plan, then
`php artisan bp:remove-teams` (it confirms first; `--force` skips that). It strips
the fenced blocks from core files (replacing `User.php`'s with its canonical
line) in the fenced files in the table above, deletes every test file that
mentions `Concise\Teams`, runs `composer remove concise-dot-digital/teams`,
drops the path repository and deletes `packages/teams`. Then do steps 5 to 7
below.

> Known issues, [#22](https://github.com/oobi/laravel-boilerplate-v2/issues/22):
> it leaves `routes/web.php` using the host variables it stripped, so the app
> doesn't boot until those route groups are fixed by hand; "every test file that
> mentions `Concise\Teams`" includes some core suites (access coverage,
> impersonation, role implications, login redirects); it reports success even
> when `composer remove` fails; and an unterminated fence truncates the file.
> Its dry run also lists `RemoveTeamsCommand.php`, whose docblock names the
> marker; it has no fence, so nothing is stripped there. Check `--dry-run` and
> `git diff` afterwards.

The steps the command performs, for doing it by hand:

1. **Composer** — remove the require and the path repository entry, then
   `composer update` (or `composer remove concise-dot-digital/teams` and delete
   the repository block).
2. **Strip the core markers** — all delete-style fences can be stripped with
   `sed -i '' '/teams:start/,/teams:end/d' <file>` — **except `User.php`**, which
   is the one *replace* case: swap its `teams:start…teams:end` block for the
   canonical trait line printed in its fence (deleting it outright would leave
   `User` with no traits). Delete the fenced blocks in every other file in the
   table: **the fenced blocks only**. The seeder files survive and keep their
   non-teams calls (`PermissionSeeder`, `SystemRolesSeeder`), which a vanilla app
   still needs; `routes/web.php` then needs its route groups pointed back at
   plain paths.
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
