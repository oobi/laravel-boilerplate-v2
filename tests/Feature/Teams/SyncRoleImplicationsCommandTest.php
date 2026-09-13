<?php

namespace Tests\Feature\Teams;

use App\Models\Role;
use Concise\Teams\Enums\TeamPermission;
use Concise\Teams\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * The data fix for a changed implication: re-closes every team role over
 * TeamPermission::implies(), additively and idempotently.
 */
class SyncRoleImplicationsCommandTest extends TestCase
{
    use RefreshDatabase;

    /** A role written around the write-time closure — what a role from before an implication change looks like. */
    private function roleHoldingOnly(string $name, TeamPermission ...$permissions): Role
    {
        $role = Team::createRole($name);
        $role->givePermissionTo(collect($permissions)
            ->map(fn (TeamPermission $permission): Permission => Permission::findOrCreate($permission->value))
            ->all());

        return $role;
    }

    public function test_it_grants_what_a_roles_held_permissions_imply(): void
    {
        $stale = $this->roleHoldingOnly('Stale', TeamPermission::MANAGE_MEMBERS, TeamPermission::UPDATE_TEAM);
        $closed = Team::createRole('Closed', [TeamPermission::MANAGE_MEMBERS]); // already holds view via createRole

        $this->artisan('bp:teams:sync-role-implications')
            ->expectsOutputToContain('Stale')
            ->expectsOutputToContain('Granted 2 permission(s) across 1 role(s).')
            ->assertSuccessful();

        $stale->refresh()->unsetRelation('permissions');
        $this->assertTrue($stale->checkPermissionTo(TeamPermission::VIEW_MEMBERS->value));
        $this->assertTrue($stale->checkPermissionTo(TeamPermission::VIEW_SETTINGS->value));
        $this->assertFalse($stale->checkPermissionTo(TeamPermission::INVITE_MEMBERS->value), 'only what is implied');
        $this->assertSame(2, $closed->refresh()->permissions->count(), 'an already-closed role is untouched');
    }

    public function test_it_does_nothing_when_every_role_is_closed(): void
    {
        Team::createRole('Closed', [TeamPermission::MANAGE_MEMBERS]);

        $this->artisan('bp:teams:sync-role-implications')
            ->expectsOutputToContain('nothing to do')
            ->assertSuccessful();
    }

    public function test_dry_run_reports_without_writing(): void
    {
        $stale = $this->roleHoldingOnly('Stale', TeamPermission::MANAGE_MEMBERS);

        $this->artisan('bp:teams:sync-role-implications', ['--dry-run' => true])
            ->expectsTable(['Role', 'Would gain'], [['Stale', TeamPermission::VIEW_MEMBERS->value]])
            ->expectsOutputToContain('Dry run')
            ->assertSuccessful();

        $stale->refresh()->unsetRelation('permissions');
        $this->assertFalse($stale->checkPermissionTo(TeamPermission::VIEW_MEMBERS->value));
    }

    public function test_it_never_removes_a_permission(): void
    {
        // A role holding a view its impliers no longer require (as after an
        // implication is removed) keeps it — it's an ordinary stored grant.
        $role = $this->roleHoldingOnly('Viewer', TeamPermission::VIEW_MEMBERS);

        $this->artisan('bp:teams:sync-role-implications')->assertSuccessful();

        $this->assertTrue($role->refresh()->checkPermissionTo(TeamPermission::VIEW_MEMBERS->value));
    }
}
