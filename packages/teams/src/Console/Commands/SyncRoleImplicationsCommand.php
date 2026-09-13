<?php

declare(strict_types=1);

namespace Concise\Teams\Console\Commands;

use App\Models\Role;
use Concise\Teams\Enums\TeamPermission;
use Concise\Teams\Models\Team;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Spatie\Permission\Guard;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * The data fix for a changed TeamPermission::implies(). The data: team roles are
 * `roles` rows (scope `team`); their grants are `role_has_permissions` rows
 * joining a role to a `permissions` row named by a TeamPermission value. Roles
 * are written closed over implies() (a grant row for "manage team members"
 * always comes with one for "view team members"), so when the map gains an
 * entry, roles written before the change are missing the new implied rows.
 *
 * This inserts exactly those rows: for each team role, one `role_has_permissions`
 * row per implied permission it lacks, creating the `permissions` row first if
 * the case is new. Nothing else is touched — not system roles, not permission
 * names that aren't TeamPermission cases, and never a deletion. If an entry is
 * REMOVED from the map, a role keeps its formerly implied grant row: that is a
 * legitimate grant now, and an admin who wants it gone unticks it on the Roles
 * screen. Silently stripping grants is the failure this codebase avoids.
 *
 * Idempotent, so it is safe on every deploy; it writes nothing when no role is
 * missing a row. --dry-run prints the roles and the rows each would gain.
 */
class SyncRoleImplicationsCommand extends Command
{
    protected $signature = 'bp:teams:sync-role-implications {--dry-run : Report what would be granted without writing}';

    protected $description = 'Grant every team role the permissions its held permissions imply (TeamPermission::implies) — the data fix after an implication changes.';

    public function handle(): int
    {
        $guard = Guard::getDefaultName(Role::class);

        /** @var Collection<int, array{role: Role, missing: list<TeamPermission>}> $gaps */
        $gaps = Team::availableRoles()
            ->with('permissions')
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role): array => ['role' => $role, 'missing' => $this->missingFor($role)])
            ->filter(fn (array $gap): bool => $gap['missing'] !== [])
            ->values();

        if ($gaps->isEmpty()) {
            $this->info('Every team role is already closed over its implications — nothing to do.');

            return self::SUCCESS;
        }

        $this->table(
            ['Role', 'Would gain'],
            $gaps->map(fn (array $gap): array => [
                $gap['role']->name,
                collect($gap['missing'])->map(fn (TeamPermission $permission): string => $permission->value)->implode(', '),
            ])->all(),
        );

        if ($this->option('dry-run')) {
            $this->line('Dry run — nothing written.');

            return self::SUCCESS;
        }

        $granted = 0;

        foreach ($gaps as $gap) {
            // Straight from the table, as Team::createRole() does — findOrCreate's
            // cache can be stale when model events are off.
            $gap['role']->givePermissionTo(collect($gap['missing'])
                ->map(fn (TeamPermission $permission): Permission => Permission::query()->firstOrCreate([
                    'name' => $permission->value,
                    'guard_name' => $guard,
                ]))
                ->all());

            $granted += count($gap['missing']);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->info(sprintf('Granted %d permission(s) across %d role(s).', $granted, $gaps->count()));

        return self::SUCCESS;
    }

    /**
     * The permissions the role's stored grants imply but it doesn't hold. Names
     * that aren't TeamPermission cases (a project's own extras, or rows left by
     * a removed case) are ignored, never touched.
     *
     * @return list<TeamPermission>
     */
    private function missingFor(Role $role): array
    {
        $held = $role->permissions->pluck('name');

        $heldCases = $held
            ->map(fn (string $name): ?TeamPermission => TeamPermission::tryFrom($name))
            ->filter()
            ->values()
            ->all();

        return collect(TeamPermission::withImplied($heldCases))
            ->reject(fn (TeamPermission $permission): bool => $held->contains($permission->value))
            ->values()
            ->all();
    }
}
