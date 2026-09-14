<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Role;
use App\Support\Roles\Implications;
use App\Support\Roles\RoleScope;
use App\Support\Roles\RoleScopeRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Spatie\Permission\Guard;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * The data fix for a changed implication map (SystemPermission::implies(),
 * TeamPermission::implies(), or any registered RoleScope's implications()).
 * The data: a role is a `roles` row in one scope; its grants are
 * `role_has_permissions` rows joining it to `permissions` rows named by the
 * scope's vocabulary. Roles are written closed over the map (a grant row for
 * "delete users" always comes with rows for "view users" and "access admin
 * panel"), so when the map gains an entry, roles written before the change are
 * missing the new implied rows and the Gate refuses them what they should hold.
 *
 * This inserts exactly those rows: for each role in each registered scope, one
 * `role_has_permissions` row per implied permission it lacks, creating the
 * `permissions` row first if the case is new. Nothing else is touched — not a
 * scope with no RoleScope registered (an add-on since removed), not permission
 * names outside the scope's map, and never a deletion. If an entry is REMOVED
 * from a map, a role keeps its formerly implied grant row: that is a legitimate
 * grant now, and an admin who wants it gone unticks it on the Roles screen.
 * Silently stripping grants is the failure this codebase avoids.
 *
 * Idempotent, so it is safe on every deploy; it writes nothing when no role is
 * missing a row. --dry-run prints the roles and the rows each would gain.
 */
class SyncRoleImplicationsCommand extends Command
{
    protected $signature = 'bp:roles:sync-implications {--dry-run : Report what would be granted without writing}';

    protected $description = 'Grant every role the permissions its held permissions imply (each RoleScope::implications()) — the data fix after an implication changes.';

    public function handle(): int
    {
        $guard = Guard::getDefaultName(Role::class);

        /** @var Collection<int, array{scope: RoleScope, role: Role, missing: list<string>}> $gaps */
        $gaps = RoleScopeRegistry::all()
            ->flatMap(fn (RoleScope $scope): Collection => Role::query()
                ->ofScope($scope->key())
                ->with('permissions')
                ->orderBy('name')
                ->get()
                ->map(fn (Role $role): array => ['scope' => $scope, 'role' => $role, 'missing' => $this->missingFor($scope, $role)]))
            ->filter(fn (array $gap): bool => $gap['missing'] !== [])
            ->values();

        if ($gaps->isEmpty()) {
            $this->info('Every role is already closed over its implications — nothing to do.');

            return self::SUCCESS;
        }

        $this->table(
            ['Scope', 'Role', 'Would gain'],
            $gaps->map(fn (array $gap): array => [
                $gap['scope']->key(),
                $gap['role']->name,
                implode(', ', $gap['missing']),
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
                ->map(fn (string $name): Permission => Permission::query()->firstOrCreate([
                    'name' => $name,
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
     * the scope's map doesn't know (a project's own extras, or rows left by a
     * removed case) are carried through the closure untouched and never added.
     *
     * @return list<string>
     */
    private function missingFor(RoleScope $scope, Role $role): array
    {
        $held = $role->permissions->pluck('name')->all();

        return Implications::impliedBy($scope->implications(), $held);
    }
}
