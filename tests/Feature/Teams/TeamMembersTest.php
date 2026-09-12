<?php

namespace Tests\Feature\Teams;

use App\Models\User;
use Concise\Teams\Enums\TeamAbility;
use Concise\Teams\Enums\TeamPermission;
use Concise\Teams\Livewire\Team\MembersTable;
use Concise\Teams\Models\Team;
use Concise\Teams\Support\TeamContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

class TeamMembersTest extends TestCase
{
    use RefreshDatabase;

    private const TEAM_ADMIN = 'Team Admin';

    private const MEMBER = 'Member';

    protected function setUp(): void
    {
        parent::setUp();

        // Fixtures via the domain method, not the seeder (tests.md): the two
        // roles a fresh install ships with.
        Team::createRole(self::TEAM_ADMIN, [
            TeamPermission::VIEW_MEMBERS,
            TeamPermission::MANAGE_MEMBERS,
            TeamPermission::INVITE_MEMBERS,
            TeamPermission::UPDATE_TEAM,
        ]);
        Team::createRole(self::MEMBER);
    }

    private function team(User $owner): Team
    {
        return Team::factory()->create(['user_id' => $owner->id]);
    }

    private function addMember(Team $team, User $user, string $role = self::MEMBER): void
    {
        $team->addMember($user, $role);
    }

    public function test_a_member_cannot_be_added_with_a_role_that_is_not_a_team_role(): void
    {
        $team = $this->team(User::factory()->create());

        $this->expectException(\InvalidArgumentException::class);

        $team->addMember(User::factory()->create(), 'Support');
    }

    public function test_creating_a_team_makes_the_owner_a_member(): void
    {
        $owner = User::factory()->create();
        $team = $this->team($owner);

        $this->assertTrue($team->hasUser($owner));
        $this->assertTrue($team->isOwnedBy($owner));
        // Ownership is structural (a shield); the owner's authority comes from the
        // default owner role they're given at setup, not from ownership itself.
        $this->assertSame(self::TEAM_ADMIN, $team->roleFor($owner));
    }

    public function test_the_owner_can_view_the_members_page(): void
    {
        $owner = User::factory()->create();
        $team = $this->team($owner);

        $this->actingAs($owner)
            ->get(route('team.members', ['team' => $team->slug]))
            ->assertOk()
            ->assertSee('Members')
            ->assertSee($owner->email);
    }

    public function test_a_team_admin_role_holder_can_manage_members_by_permission(): void
    {
        $owner = User::factory()->create();
        $team = $this->team($owner);
        $admin = User::factory()->create();
        $this->addMember($team, $admin, self::TEAM_ADMIN);

        $this->actingAs($admin)
            ->get(route('team.members', ['team' => $team->slug]))
            ->assertOk();
    }

    public function test_a_view_members_holder_can_open_the_page_the_sidebar_links_to(): void
    {
        // The nav item, the page and the table all gate on the same ability, so a
        // link never leads to a 403 — a viewer just sees the roster without actions.
        Team::createRole('Viewer', [TeamPermission::VIEW_MEMBERS]);
        $owner = User::factory()->create();
        $team = $this->team($owner);
        $viewer = User::factory()->create();
        $this->addMember($team, $viewer, 'Viewer');

        $this->actingAs($viewer)
            ->get(route('team.dashboard', ['team' => $team->slug]))
            ->assertOk()
            ->assertSee(route('team.members', ['team' => $team->slug]), false);

        $this->actingAs($viewer)
            ->get(route('team.members', ['team' => $team->slug]))
            ->assertOk();
    }

    public function test_a_regular_member_cannot_manage_members(): void
    {
        $owner = User::factory()->create();
        $team = $this->team($owner);
        $member = User::factory()->create();
        $this->addMember($team, $member);

        $this->actingAs($member)
            ->get(route('team.members', ['team' => $team->slug]))
            ->assertForbidden();
    }

    public function test_an_admin_can_change_a_members_role(): void
    {
        $owner = User::factory()->create();
        $team = $this->team($owner);
        $member = User::factory()->create();
        $this->addMember($team, $member);

        Livewire::actingAs($owner)
            ->test(MembersTable::class, ['team' => $team])
            ->callTableAction('changeRole', $member, data: ['roles' => self::TEAM_ADMIN])
            ->assertHasNoTableActionErrors();

        $this->assertSame(self::TEAM_ADMIN, $team->roleFor($member));
    }

    public function test_an_unknown_role_is_rejected(): void
    {
        $owner = User::factory()->create();
        $team = $this->team($owner);
        $member = User::factory()->create();
        $this->addMember($team, $member);

        Livewire::actingAs($owner)
            ->test(MembersTable::class, ['team' => $team])
            ->callTableAction('changeRole', $member, data: ['roles' => 'Superuser'])
            ->assertHasTableActionErrors(['roles']);

        $this->assertSame(self::MEMBER, $team->roleFor($member));
    }

    public function test_a_member_holds_one_role_unless_the_project_allows_several(): void
    {
        $team = $this->team(User::factory()->create());
        $member = User::factory()->create();
        $this->addMember($team, $member);

        try {
            $team->syncMemberRoles($member, [self::TEAM_ADMIN, self::MEMBER]);
            $this->fail('two roles should be refused in single-role mode');
        } catch (\InvalidArgumentException) {
            $this->assertSame([self::MEMBER], $team->rolesFor($member)->all());
        }

        config(['teams.multiple_roles_per_member' => true]);

        $team->syncMemberRoles($member, [self::TEAM_ADMIN, self::MEMBER]);

        $this->assertEqualsCanonicalizing([self::TEAM_ADMIN, self::MEMBER], $team->rolesFor($member)->all());
        $this->assertEqualsCanonicalizing([self::TEAM_ADMIN, self::MEMBER], $team->memberRoles()->get($member->id));
    }

    public function test_the_role_picker_follows_the_projects_cardinality(): void
    {
        config(['teams.multiple_roles_per_member' => true]);
        $owner = User::factory()->create();
        $team = $this->team($owner);
        $member = User::factory()->create();
        $this->addMember($team, $member);

        Livewire::actingAs($owner)
            ->test(MembersTable::class, ['team' => $team])
            ->callTableAction('changeRole', $member, data: ['roles' => [self::TEAM_ADMIN, self::MEMBER]])
            ->assertHasNoTableActionErrors();

        $this->assertCount(2, $team->rolesFor($member));
    }

    public function test_an_admin_can_remove_a_member(): void
    {
        $owner = User::factory()->create();
        $team = $this->team($owner);
        $member = User::factory()->create();
        $this->addMember($team, $member);

        Livewire::actingAs($owner)
            ->test(MembersTable::class, ['team' => $team])
            ->callTableAction('remove', $member);

        $this->assertFalse($team->fresh()->hasUser($member));
        $this->assertNull($team->roleFor($member));
    }

    public function test_the_owner_cannot_be_removed_from_their_own_team(): void
    {
        $owner = User::factory()->create();
        $team = $this->team($owner);

        Livewire::actingAs($owner)
            ->test(MembersTable::class, ['team' => $team])
            ->assertTableActionHidden('remove', $owner);

        $team->removeMember($owner); // the domain method is a no-op for the owner too

        $this->assertTrue($team->fresh()->hasUser($owner));
    }

    public function test_a_regular_member_cannot_use_the_members_table(): void
    {
        $owner = User::factory()->create();
        $team = $this->team($owner);
        $member = User::factory()->create();
        $this->addMember($team, $member);

        Livewire::actingAs($member)
            ->test(MembersTable::class, ['team' => $team])
            ->assertForbidden();
    }

    public function test_a_stale_role_row_grants_nothing_to_an_ex_member(): void
    {
        // Membership is the prerequisite for every team ability: a detach path
        // that isn't removeMember() (an import, a support script) leaves the
        // model_has_roles row behind, and that row must not keep granting.
        $owner = User::factory()->create();
        $team = $this->team($owner);
        $user = User::factory()->create();
        $this->addMember($team, $user, self::TEAM_ADMIN);
        $this->assertTrue(Gate::forUser($user)->allows(TeamAbility::MANAGE_MEMBERS, $team));

        $team->users()->detach($user->getKey());

        foreach ([TeamAbility::VIEW, TeamAbility::VIEW_MEMBERS, TeamAbility::MANAGE_MEMBERS, TeamAbility::UPDATE] as $ability) {
            $this->assertFalse(Gate::forUser($user)->allows($ability, $team), $ability->value);
        }
    }

    public function test_a_view_members_holder_sees_the_roster_but_has_no_actions(): void
    {
        Team::createRole('Viewer', [TeamPermission::VIEW_MEMBERS]);
        $owner = User::factory()->create();
        $team = $this->team($owner);
        $viewer = User::factory()->create();
        $this->addMember($team, $viewer, 'Viewer');
        $other = User::factory()->create();
        $this->addMember($team, $other);

        Livewire::actingAs($viewer)
            ->test(MembersTable::class, ['team' => $team])
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$owner, $viewer, $other])
            ->assertTableActionHidden('changeRole', $other)
            ->assertTableActionHidden('remove', $other);
    }

    protected function tearDown(): void
    {
        app(TeamContext::class)->clear();

        parent::tearDown();
    }
}
