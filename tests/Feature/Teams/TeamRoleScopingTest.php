<?php

namespace Tests\Feature\Teams;

use App\Enums\SystemPermission;
use App\Models\Role;
use App\Models\User;
use Concise\Teams\Models\Team;
use Concise\Teams\Support\TeamPermissionResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The RBAC keystone (~dev/TEAMS_TIER_SCOPE.md §5): system roles and per-team
 * roles coexist on one user, each resolving only in its own scope, using
 * spatie's teams feature with a reserved system scope (team id 0).
 */
class TeamRoleScopingTest extends TestCase
{
    use RefreshDatabase;

    /** Set the active permissions team scope, then reload the user's roles for it. */
    private function scope(int|string|null $teamId, User $user): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($teamId);
        $user->unsetRelation('roles');
    }

    public function test_the_default_scope_is_the_reserved_system_id(): void
    {
        $this->assertSame(
            TeamPermissionResolver::SYSTEM_SCOPE,
            app(PermissionRegistrar::class)->getPermissionsTeamId(),
        );
    }

    public function test_a_user_holds_a_system_role_and_a_team_role_at_once(): void
    {
        $user = User::factory()->create();

        // System role (default scope 0).
        $support = Role::findOrCreate('support');
        $support->givePermissionTo(Permission::findOrCreate(SystemPermission::MANAGE_USERS->value));
        $user->assignRole($support);

        // Team role (scope N).
        $team = Team::factory()->create();
        $this->scope($team->id, $user);
        $user->assignRole(Role::findOrCreate('editor'));

        // In the team scope: the team role resolves, the system role does not.
        $this->assertTrue($user->hasRole('editor'));
        $this->assertFalse($user->hasRole('support'));
        $this->assertFalse($user->checkPermissionTo(SystemPermission::MANAGE_USERS->value));

        // Back in the system scope: the system role and its permission resolve,
        // the team role does not.
        $this->scope(TeamPermissionResolver::SYSTEM_SCOPE, $user);
        $this->assertTrue($user->hasRole('support'));
        $this->assertFalse($user->hasRole('editor'));
        $this->assertTrue($user->checkPermissionTo(SystemPermission::MANAGE_USERS->value));
    }

    public function test_a_user_can_hold_different_roles_in_different_teams(): void
    {
        $user = User::factory()->create();
        $teamA = Team::factory()->create();
        $teamB = Team::factory()->create();

        $this->scope($teamA->id, $user);
        $user->assignRole(Role::findOrCreate('editor'));

        $this->scope($teamB->id, $user);
        $user->assignRole(Role::findOrCreate('viewer'));

        $this->scope($teamA->id, $user);
        $this->assertTrue($user->hasRole('editor'));
        $this->assertFalse($user->hasRole('viewer'));

        $this->scope($teamB->id, $user);
        $this->assertTrue($user->hasRole('viewer'));
        $this->assertFalse($user->hasRole('editor'));
    }

    protected function tearDown(): void
    {
        // Reset scope so it never leaks between tests.
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        parent::tearDown();
    }
}
