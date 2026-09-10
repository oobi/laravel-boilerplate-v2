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
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

/** A suspended member keeps membership and role but can't use the team until reinstated. */
class TeamSuspensionTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $teamAdmin;

    private User $member;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        Team::createRole('Team Admin', [TeamPermission::MANAGE_MEMBERS]);
        Team::createRole('Member');

        $this->owner = User::factory()->create();
        $this->team = Team::factory()->ownedBy($this->owner)->create();
        $this->teamAdmin = User::factory()->create();
        $this->team->addMember($this->teamAdmin, 'Team Admin');
        $this->member = User::factory()->create();
        $this->team->addMember($this->member, 'Member');
    }

    public function test_a_team_admin_can_suspend_and_reinstate_a_member(): void
    {
        $component = Livewire::actingAs($this->teamAdmin)
            ->test(MembersTable::class, ['team' => $this->team])
            ->assertTableActionVisible('suspend', $this->member)
            ->assertTableActionHidden('reinstate', $this->member)
            ->callTableAction('suspend', $this->member);

        $this->assertTrue($this->team->isSuspended($this->member));
        $this->assertTrue($this->team->hasUser($this->member), 'still a member');
        $this->assertSame('Member', $this->team->roleFor($this->member), 'role kept');

        $component
            ->assertTableActionHidden('suspend', $this->member)
            ->assertTableActionVisible('reinstate', $this->member)
            ->assertSee('Suspended')
            ->callTableAction('reinstate', $this->member);

        $this->assertFalse($this->team->isSuspended($this->member));
    }

    public function test_a_suspended_member_cannot_enter_the_team(): void
    {
        $this->team->suspendMember($this->member);

        $this->actingAs($this->member)
            ->get(route('team.dashboard', ['team' => $this->team->slug]))
            ->assertForbidden();

        $this->assertFalse(Gate::forUser($this->member)->allows(TeamAbility::VIEW, $this->team));
    }

    public function test_the_entry_point_and_switcher_skip_a_team_the_user_is_suspended_in(): void
    {
        $other = Team::factory()->create(['name' => 'Other Co']);
        $other->addMember($this->member);
        $this->member->switchTeam($this->team);
        $this->team->suspendMember($this->member);

        $this->actingAs($this->member)
            ->get(route('team.index'))
            ->assertRedirect(route('team.dashboard', ['team' => $other->slug]));

        $this->actingAs($this->member)
            ->get(route('team.dashboard', ['team' => $other->slug]))
            ->assertOk()
            ->assertDontSee($this->team->name);
    }

    public function test_a_suspended_team_admin_loses_their_permissions_until_reinstated(): void
    {
        $this->team->suspendMember($this->teamAdmin);
        $this->assertFalse(Gate::forUser($this->teamAdmin)->allows(TeamAbility::MANAGE_MEMBERS, $this->team));

        $this->team->reinstateMember($this->teamAdmin);
        $this->assertTrue(Gate::forUser($this->teamAdmin)->allows(TeamAbility::MANAGE_MEMBERS, $this->team));
    }

    public function test_a_suspended_co_owner_loses_the_bypass_until_reinstated(): void
    {
        $this->team->makeOwner($this->member);
        $this->team->suspendMember($this->member);

        $this->assertTrue($this->team->isOwnedBy($this->member), 'the flag is kept');
        $this->assertFalse(Gate::forUser($this->member)->allows(TeamAbility::MANAGE_MEMBERS, $this->team));
    }

    public function test_the_primary_owner_cannot_be_suspended(): void
    {
        Livewire::actingAs($this->teamAdmin)
            ->test(MembersTable::class, ['team' => $this->team])
            ->assertTableActionHidden('suspend', $this->owner);

        $this->expectException(InvalidArgumentException::class);

        $this->team->suspendMember($this->owner);
    }

    public function test_only_someone_who_can_demote_a_co_owner_may_suspend_them(): void
    {
        $this->team->makeOwner($this->member);

        Livewire::actingAs($this->teamAdmin)
            ->test(MembersTable::class, ['team' => $this->team])
            ->assertTableActionHidden('suspend', $this->member);

        Livewire::actingAs($this->owner)
            ->test(MembersTable::class, ['team' => $this->team])
            ->assertTableActionVisible('suspend', $this->member)
            ->callTableAction('suspend', $this->member);

        $this->assertTrue($this->team->isSuspended($this->member));
    }

    public function test_the_memberships_panel_shows_the_suspension(): void
    {
        $this->team->suspendMember($this->member);

        $this->actingAs(User::factory()->superAdmin()->create())
            ->get(route('users.show', $this->member))
            ->assertOk()
            ->assertSee('Suspended');
    }

    protected function tearDown(): void
    {
        app(TeamContext::class)->clear();

        parent::tearDown();
    }
}
