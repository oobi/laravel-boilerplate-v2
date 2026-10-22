<?php

namespace Tests\Feature\Teams;

use App\Enums\SystemPermission;
use App\Models\Role;
use Concise\Teams\Enums\TeamPermission;
use Concise\Teams\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * bp:roles:sync-implications over the teams tier's role scope, alongside
 * core's (Tests\Feature\Console\SyncRoleImplicationsCommandTest).
 */
class TeamRoleImplicationsSyncTest extends TestCase
{
    use RefreshDatabase;

    /** A team role written around the write-time closure: what a role from before an implication change looks like. */
    private function teamRoleHoldingOnly(string $name, TeamPermission ...$permissions): Role
    {
        $role = Team::createRole($name);
        $role->givePermissionTo(collect($permissions)
            ->map(fn (TeamPermission $permission): Permission => Permission::findOrCreate($permission->value))
            ->all());

        return $role;
    }

    public function test_it_grants_what_a_roles_held_permissions_imply_in_every_scope(): void
    {
        $staleTeam = $this->teamRoleHoldingOnly('Stale', TeamPermission::MANAGE_MEMBERS, TeamPermission::UPDATE_TEAM);
        $closedTeam = Team::createRole('Closed', [TeamPermission::MANAGE_MEMBERS]); // already holds view via createRole
        $staleSystem = Role::create(['name' => 'Desk', 'guard_name' => 'web']);
        $staleSystem->givePermissionTo(Permission::findOrCreate(SystemPermission::DELETE_USERS->value));

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
    }

    public function test_dry_run_reports_a_team_role_under_its_scope(): void
    {
        $stale = $this->teamRoleHoldingOnly('Stale', TeamPermission::MANAGE_MEMBERS);

        $this->artisan('bp:roles:sync-implications', ['--dry-run' => true])
            ->expectsTable(['Scope', 'Role', 'Would gain'], [[Team::ROLE_SCOPE, 'Stale', TeamPermission::VIEW_MEMBERS->value]])
            ->assertSuccessful();

        $stale->refresh()->unsetRelation('permissions');
        $this->assertFalse($stale->checkPermissionTo(TeamPermission::VIEW_MEMBERS->value));
    }
}
