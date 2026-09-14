<?php

namespace Tests\Feature\Teams;

use App\Enums\SystemPermission;
use App\Models\Role;
use App\Models\User;
use Concise\Teams\Enums\TeamAbility;
use Concise\Teams\Enums\TeamPermission;
use Concise\Teams\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Team roles are assigned on the membership pivot (team_user_role), not
 * through spatie's teams feature — so system roles are stock spatie (R5: a
 * user holds a system role and team roles at once, each answering only its own
 * question), a user holds a different role in each team (R4), and an
 * assignment can't outlive the membership or the role it points at.
 */
class TeamMemberRolesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Team::createRole('Editor', [TeamPermission::MANAGE_MEMBERS]);
        Team::createRole('Viewer', [TeamPermission::VIEW_MEMBERS]);
    }

    public function test_a_user_holds_a_system_role_and_a_team_role_at_once_and_neither_answers_for_the_other(): void
    {
        $user = User::factory()->create();

        $support = Role::findOrCreate('Support');
        $support->givePermissionTo(Permission::findOrCreate(SystemPermission::MANAGE_USERS->value));
        $user->assignRole($support);

        $team = Team::factory()->create();
        $team->addMember($user, 'Editor');

        // Stock spatie for the system side: unaffected by the team role, in or out of a team route.
        $this->assertTrue($user->hasRole('Support'));
        $this->assertFalse($user->hasRole('Editor'), 'a team role is not a spatie assignment on the user');
        $this->assertTrue($user->checkPermissionTo(SystemPermission::MANAGE_USERS->value));
        $this->assertFalse($user->checkPermissionTo(TeamPermission::MANAGE_MEMBERS->value), 'a team permission never answers on the user');

        // The team side reads the pivot.
        $this->assertSame('Editor', $team->roleFor($user));
        $this->assertTrue(Gate::forUser($user)->allows(TeamAbility::MANAGE_MEMBERS, $team));
    }

    public function test_a_user_holds_a_different_role_in_each_team(): void
    {
        $user = User::factory()->create();
        $a = Team::factory()->create();
        $b = Team::factory()->create();
        $a->addMember($user, 'Editor');
        $b->addMember($user, 'Viewer');

        $this->assertSame('Editor', $a->roleFor($user));
        $this->assertSame('Viewer', $b->roleFor($user));
        $this->assertTrue(Gate::forUser($user)->allows(TeamAbility::MANAGE_MEMBERS, $a));
        $this->assertFalse(Gate::forUser($user)->allows(TeamAbility::MANAGE_MEMBERS, $b));
    }

    public function test_a_role_cannot_be_assigned_to_a_non_member(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('is not a member of');

        $team->syncMemberRoles($user, ['Editor']);
    }

    public function test_an_assignment_goes_with_the_membership(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();
        $team->addMember($user, 'Editor');
        $membershipId = $team->membershipOf($user)->id;

        $team->users()->detach($user->getKey()); // any detach path, not just removeMember()

        $this->assertDatabaseMissing('team_user_role', ['team_user_id' => $membershipId]);
        $this->assertTrue($team->rolesFor($user->fresh())->isEmpty());
    }

    public function test_an_assignment_goes_with_the_role(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();
        $team->addMember($user, 'Editor');

        Team::availableRoles()->where('name', 'Editor')->firstOrFail()->delete();

        $this->assertTrue($team->rolesFor($user)->isEmpty());
        $this->assertFalse(Gate::forUser($user)->allows(TeamAbility::MANAGE_MEMBERS, $team));
    }

    public function test_repeated_ability_checks_read_the_membership_once_per_request(): void
    {
        // A page asks "may this person do X here?" many times, through however
        // many Team instances the request holds (middleware, layout, component).
        // The membership row and its roles are loaded once on the USER — the one
        // instance a request has — and reused, as spatie does with roles.
        $user = User::factory()->create();
        $team = Team::factory()->create();
        $team->addMember($user, 'Editor');
        $user = $user->fresh(); // cold, as a request receives it
        $instances = [$team->fresh(), $team->fresh(), Team::query()->findOrFail($team->id)];
        Gate::forUser(User::factory()->create())->allows(TeamAbility::VIEW, $team); // warm spatie's permission cache, as a running app has

        DB::flushQueryLog();
        DB::enableQueryLog();

        foreach ($instances as $instance) {
            foreach ([TeamAbility::VIEW, TeamAbility::VIEW_MEMBERS, TeamAbility::MANAGE_MEMBERS, TeamAbility::INVITE, TeamAbility::UPDATE] as $ability) {
                Gate::forUser($user)->allows($ability, $instance);
            }
        }

        $queries = collect(DB::getQueryLog())->pluck('query');
        DB::disableQueryLog();

        // One for the membership row, one for its roles — then reuse across every Team
        // instance; the role → permission half comes from spatie's cache, never a query.
        $this->assertCount(2, $queries, $queries->implode("\n"));
        $this->assertTrue($queries->every(fn (string $sql): bool => str_contains($sql, 'team_user')));

        // A write forgets the memo, so the next check sees it.
        $team->syncMemberRoles($user, ['Viewer']);
        $this->assertFalse(Gate::forUser($user)->allows(TeamAbility::MANAGE_MEMBERS, $team));
        $team->suspendMember($user);
        $this->assertFalse(Gate::forUser($user)->allows(TeamAbility::VIEW_MEMBERS, $team));
        $team->reinstateMember($user);
        $this->assertTrue(Gate::forUser($user)->allows(TeamAbility::VIEW_MEMBERS, $team));
    }

    public function test_the_members_role_map_reads_every_assignment_in_one_query(): void
    {
        $team = Team::factory()->create();
        $editor = User::factory()->create();
        $viewer = User::factory()->create();
        $none = User::factory()->create();
        $team->addMember($editor, 'Editor');
        $team->addMember($viewer, 'Viewer');
        $team->addMember($none);

        $map = $team->memberRoles();

        $this->assertSame(['Editor'], $map->get($editor->id));
        $this->assertSame(['Viewer'], $map->get($viewer->id));
        $this->assertNull($map->get($none->id));
    }
}
