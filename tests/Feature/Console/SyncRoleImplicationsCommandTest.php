<?php

namespace Tests\Feature\Console;

use App\Enums\SystemPermission;
use App\Models\Role;
use Concise\Teams\Enums\TeamPermission;
use Concise\Teams\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * The data fix for a changed implication: re-closes every role in every
 * registered scope over its RoleScope::implications(), additively and
 * idempotently, following the chain.
 */
class SyncRoleImplicationsCommandTest extends TestCase
{
    use RefreshDatabase;

    /** A team role written around the write-time closure — what a role from before an implication change looks like. */
    private function teamRoleHoldingOnly(string $name, TeamPermission ...$permissions): Role
    {
        $role = Team::createRole($name);
        $role->givePermissionTo(collect($permissions)
            ->map(fn (TeamPermission $permission): Permission => Permission::findOrCreate($permission->value))
            ->all());

        return $role;
    }

    /** A system role written around the closure. */
    private function systemRoleHoldingOnly(string $name, SystemPermission ...$permissions): Role
    {
        $role = Role::create(['name' => $name, 'guard_name' => 'web']);
        $role->givePermissionTo(collect($permissions)
            ->map(fn (SystemPermission $permission): Permission => Permission::findOrCreate($permission->value))
            ->all());

        return $role;
    }

    public function test_it_grants_what_a_roles_held_permissions_imply_in_every_scope(): void
    {
        $staleTeam = $this->teamRoleHoldingOnly('Stale', TeamPermission::MANAGE_MEMBERS, TeamPermission::UPDATE_TEAM);
        $closedTeam = Team::createRole('Closed', [TeamPermission::MANAGE_MEMBERS]); // already holds view via createRole
        $staleSystem = $this->systemRoleHoldingOnly('Desk', SystemPermission::DELETE_USERS);

        $this->artisan('bp:roles:sync-implications')
            ->expectsOutputToContain('Stale')
            ->expectsOutputToContain('Desk')
            ->expectsOutputToContain('Granted 4 permission(s) across 2 role(s).')
            ->assertSuccessful();

        $staleTeam->refresh()->unsetRelation('permissions');
        $this->assertTrue($staleTeam->checkPermissionTo(TeamPermission::VIEW_MEMBERS->value));
        $this->assertTrue($staleTeam->checkPermissionTo(TeamPermission::VIEW_SETTINGS->value));
        $this->assertFalse($staleTeam->checkPermissionTo(TeamPermission::INVITE_MEMBERS->value), 'only what is implied');
        $this->assertSame(2, $closedTeam->refresh()->permissions->count(), 'an already-closed role is untouched');

        // The system chain is two hops: delete users → view users → access admin panel.
        $staleSystem->refresh()->unsetRelation('permissions');
        $this->assertTrue($staleSystem->checkPermissionTo(SystemPermission::VIEW_USERS->value));
        $this->assertTrue($staleSystem->checkPermissionTo(SystemPermission::ACCESS_ADMIN_PANEL->value));
        $this->assertFalse($staleSystem->checkPermissionTo(SystemPermission::MANAGE_USERS->value), 'only what is implied');
    }

    public function test_it_does_nothing_when_every_role_is_closed(): void
    {
        Team::createRole('Closed', [TeamPermission::MANAGE_MEMBERS]);
        $this->systemRoleHoldingOnly('Reader', SystemPermission::ACCESS_ADMIN_PANEL);

        $this->artisan('bp:roles:sync-implications')
            ->expectsOutputToContain('nothing to do')
            ->assertSuccessful();
    }

    public function test_dry_run_reports_without_writing(): void
    {
        $stale = $this->teamRoleHoldingOnly('Stale', TeamPermission::MANAGE_MEMBERS);

        $this->artisan('bp:roles:sync-implications', ['--dry-run' => true])
            ->expectsTable(['Scope', 'Role', 'Would gain'], [[Team::ROLE_SCOPE, 'Stale', TeamPermission::VIEW_MEMBERS->value]])
            ->expectsOutputToContain('Dry run')
            ->assertSuccessful();

        $stale->refresh()->unsetRelation('permissions');
        $this->assertFalse($stale->checkPermissionTo(TeamPermission::VIEW_MEMBERS->value));
    }

    public function test_it_never_removes_a_permission(): void
    {
        // A role holding a view its impliers no longer require (as after an
        // implication is removed) keeps it — it's an ordinary stored grant.
        $role = $this->teamRoleHoldingOnly('Viewer', TeamPermission::VIEW_MEMBERS);

        $this->artisan('bp:roles:sync-implications')->assertSuccessful();

        $this->assertTrue($role->refresh()->checkPermissionTo(TeamPermission::VIEW_MEMBERS->value));
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
