<?php

namespace Tests\Feature\Console;

use App\Enums\SystemPermission;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * The data fix for a changed implication: re-closes every role in every
 * registered scope over its RoleScope::implications(), additively and
 * idempotently, following the chain. Core's system scope here; the teams
 * tier's scope in Tests\Feature\Teams\TeamRoleImplicationsSyncTest.
 */
class SyncRoleImplicationsCommandTest extends TestCase
{
    use RefreshDatabase;

    /** A system role written around the closure: what a role from before an implication change looks like. */
    private function systemRoleHoldingOnly(string $name, SystemPermission ...$permissions): Role
    {
        $role = Role::create(['name' => $name, 'guard_name' => 'web']);
        $role->givePermissionTo(collect($permissions)
            ->map(fn (SystemPermission $permission): Permission => Permission::findOrCreate($permission->value))
            ->all());

        return $role;
    }

    public function test_it_grants_what_a_roles_held_permissions_imply(): void
    {
        $stale = $this->systemRoleHoldingOnly('Desk', SystemPermission::DELETE_USERS);
        $closed = $this->systemRoleHoldingOnly('Reader', SystemPermission::VIEW_USERS, SystemPermission::ACCESS_ADMIN_PANEL);

        $this->artisan('bp:roles:sync-implications')
            ->expectsOutputToContain('Desk')
            ->expectsOutputToContain('Granted 2 permission(s) across 1 role(s).')
            ->assertSuccessful();

        // The chain is two hops: delete users → view users → access admin panel.
        $stale->refresh()->unsetRelation('permissions');
        $this->assertTrue($stale->checkPermissionTo(SystemPermission::VIEW_USERS->value));
        $this->assertTrue($stale->checkPermissionTo(SystemPermission::ACCESS_ADMIN_PANEL->value));
        $this->assertFalse($stale->checkPermissionTo(SystemPermission::MANAGE_USERS->value), 'only what is implied');
        $this->assertSame(2, $closed->refresh()->permissions->count(), 'an already-closed role is untouched');
    }

    public function test_it_does_nothing_when_every_role_is_closed(): void
    {
        $this->systemRoleHoldingOnly('Reader', SystemPermission::ACCESS_ADMIN_PANEL);

        $this->artisan('bp:roles:sync-implications')
            ->expectsOutputToContain('nothing to do')
            ->assertSuccessful();
    }

    public function test_dry_run_reports_without_writing(): void
    {
        $stale = $this->systemRoleHoldingOnly('Stale', SystemPermission::VIEW_USERS);

        $this->artisan('bp:roles:sync-implications', ['--dry-run' => true])
            ->expectsTable(['Scope', 'Role', 'Would gain'], [[Role::SYSTEM_SCOPE, 'Stale', SystemPermission::ACCESS_ADMIN_PANEL->value]])
            ->expectsOutputToContain('Dry run')
            ->assertSuccessful();

        $stale->refresh()->unsetRelation('permissions');
        $this->assertFalse($stale->checkPermissionTo(SystemPermission::ACCESS_ADMIN_PANEL->value));
    }

    public function test_it_never_removes_a_permission(): void
    {
        // A role holding a permission its impliers no longer require (as after an
        // implication is removed) keeps it: it's an ordinary stored grant.
        $role = $this->systemRoleHoldingOnly('Viewer', SystemPermission::ACCESS_ADMIN_PANEL);

        $this->artisan('bp:roles:sync-implications')->assertSuccessful();

        $this->assertTrue($role->refresh()->checkPermissionTo(SystemPermission::ACCESS_ADMIN_PANEL->value));
    }

    public function test_a_role_whose_scope_is_not_registered_is_left_alone(): void
    {
        $orphan = Role::create(['name' => 'Orphan', 'guard_name' => 'web', 'scope' => 'gone']);
        $orphan->givePermissionTo(Permission::findOrCreate(SystemPermission::DELETE_USERS->value));

        $this->artisan('bp:roles:sync-implications')
            ->expectsOutputToContain('nothing to do')
            ->assertSuccessful();

        $this->assertSame(1, $orphan->refresh()->permissions->count());
    }
}
