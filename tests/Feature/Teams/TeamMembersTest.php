<?php

namespace Tests\Feature\Teams;

use App\Models\User;
use Concise\Teams\Enums\TeamPermission;
use Concise\Teams\Livewire\Team\ListMembers;
use Concise\Teams\Models\Team;
use Concise\Teams\Support\TeamContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        // Ownership is structural, not a role.
        $this->assertNull($team->roleFor($owner));
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
            ->test(ListMembers::class, ['team' => $team])
            ->call('changeRole', $member->id, self::TEAM_ADMIN);

        $this->assertSame(self::TEAM_ADMIN, $team->roleFor($member));
    }

    public function test_an_unknown_role_is_ignored(): void
    {
        $owner = User::factory()->create();
        $team = $this->team($owner);
        $member = User::factory()->create();
        $this->addMember($team, $member);

        Livewire::actingAs($owner)
            ->test(ListMembers::class, ['team' => $team])
            ->call('changeRole', $member->id, 'Superuser');

        $this->assertSame(self::MEMBER, $team->roleFor($member));
    }

    public function test_an_admin_can_remove_a_member(): void
    {
        $owner = User::factory()->create();
        $team = $this->team($owner);
        $member = User::factory()->create();
        $this->addMember($team, $member);

        Livewire::actingAs($owner)
            ->test(ListMembers::class, ['team' => $team])
            ->call('removeMember', $member->id);

        $this->assertFalse($team->fresh()->hasUser($member));
        $this->assertNull($team->roleFor($member));
    }

    public function test_the_owner_cannot_be_removed_from_their_own_team(): void
    {
        $owner = User::factory()->create();
        $team = $this->team($owner);

        Livewire::actingAs($owner)
            ->test(ListMembers::class, ['team' => $team])
            ->call('removeMember', $owner->id);

        $this->assertTrue($team->fresh()->hasUser($owner));
    }

    public function test_a_regular_member_cannot_call_member_actions(): void
    {
        $owner = User::factory()->create();
        $team = $this->team($owner);
        $member = User::factory()->create();
        $this->addMember($team, $member);

        Livewire::actingAs($member)
            ->test(ListMembers::class, ['team' => $team])
            ->assertForbidden();
    }

    protected function tearDown(): void
    {
        app(TeamContext::class)->clear();

        parent::tearDown();
    }
}
