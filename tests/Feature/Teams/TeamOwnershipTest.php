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

/**
 * The Slack model: one primary owner (teams.user_id — transferable, and alone
 * able to delete the team, transfer it, or manage owners) plus co-owners.
 * Ownership is a shield (other members can't remove or demote an owner), never a
 * permission bypass — an owner's day-to-day authority comes from their team role,
 * exactly like any member.
 */
class TeamOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private User $primary;

    private User $coOwner;

    private User $member;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        Team::createRole('Team Admin', [TeamPermission::MANAGE_MEMBERS]);
        Team::createRole('Member');

        $this->primary = User::factory()->create();
        $this->team = Team::factory()->ownedBy($this->primary)->create();
        $this->coOwner = User::factory()->create();
        $this->team->addMember($this->coOwner);
        $this->team->makeOwner($this->coOwner);
        $this->member = User::factory()->create();
        $this->team->addMember($this->member, 'Member');
    }

    public function test_a_co_owner_has_no_bypass_authority_comes_from_a_role(): void
    {
        $this->assertTrue($this->team->isOwnedBy($this->coOwner));
        $this->assertFalse($this->team->isPrimaryOwner($this->coOwner));

        // Shielded, not empowered: with no role a co-owner holds no team permissions.
        $this->assertFalse(Gate::forUser($this->coOwner)->allows(TeamAbility::MANAGE_MEMBERS, $this->team));
        $this->assertFalse(Gate::forUser($this->coOwner)->allows(TeamAbility::UPDATE, $this->team));

        // Grant a role → the authority comes from it, like any member.
        $this->team->syncMemberRoles($this->coOwner, ['Team Admin']);
        $this->assertTrue(Gate::forUser($this->coOwner)->allows(TeamAbility::MANAGE_MEMBERS, $this->team));
    }

    public function test_only_the_primary_owner_may_delete_transfer_or_manage_owners(): void
    {
        foreach ([TeamAbility::DELETE, TeamAbility::TRANSFER_OWNERSHIP, TeamAbility::MANAGE_OWNERS] as $ability) {
            $this->assertTrue(Gate::forUser($this->primary)->allows($ability, $this->team), $ability->value);
            $this->assertFalse(Gate::forUser($this->coOwner)->allows($ability, $this->team), $ability->value);
            $this->assertFalse(Gate::forUser($this->member)->allows($ability, $this->team), $ability->value);
        }
    }

    public function test_the_primary_owner_can_promote_and_demote_co_owners(): void
    {
        $component = Livewire::actingAs($this->primary)
            ->test(MembersTable::class, ['team' => $this->team])
            ->assertTableActionVisible('makeOwner', $this->member)
            ->assertTableActionHidden('makeOwner', $this->coOwner)
            ->callTableAction('makeOwner', $this->member);

        $this->assertTrue($this->team->isOwnedBy($this->member));

        $component
            ->assertTableActionVisible('revokeOwner', $this->member)
            ->assertTableActionHidden('revokeOwner', $this->primary)
            ->callTableAction('revokeOwner', $this->member);

        $this->assertFalse($this->team->isOwnedBy($this->member));
        $this->assertTrue($this->team->hasUser($this->member), 'demotion keeps membership');
    }

    public function test_a_co_owner_sees_no_ownership_actions(): void
    {
        // A co-owner needs a role to reach the roster at all; even with manage-members
        // the ownership acts stay out of reach (primary owner / system admin only).
        $this->team->syncMemberRoles($this->coOwner, ['Team Admin']);

        Livewire::actingAs($this->coOwner)
            ->test(MembersTable::class, ['team' => $this->team])
            ->assertTableActionHidden('makeOwner', $this->member)
            ->assertTableActionHidden('transferOwnership', $this->member)
            ->assertTableActionHidden('revokeOwner', $this->coOwner);
    }

    public function test_the_primary_owner_cannot_be_demoted(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->team->revokeOwner($this->primary);
    }

    public function test_transferring_ownership_makes_the_previous_primary_owner_a_co_owner(): void
    {
        Livewire::actingAs($this->primary)
            ->test(MembersTable::class, ['team' => $this->team])
            ->assertTableActionHidden('transferOwnership', $this->primary)
            ->callTableAction('transferOwnership', $this->member);

        $this->team->refresh();
        $this->assertTrue($this->team->isPrimaryOwner($this->member));
        $this->assertTrue($this->team->isOwnedBy($this->primary), 'previous primary stays a co-owner');
        $this->assertFalse($this->team->isPrimaryOwner($this->primary));
    }

    public function test_a_system_admin_can_transfer_ownership_without_being_a_member(): void
    {
        $admin = User::factory()->superAdmin()->create();

        Livewire::actingAs($admin)
            ->test(MembersTable::class, ['team' => $this->team])
            ->callTableAction('transferOwnership', $this->member);

        $this->assertTrue($this->team->fresh()->isPrimaryOwner($this->member));
    }

    public function test_only_someone_who_can_demote_a_co_owner_may_remove_them(): void
    {
        $teamAdmin = User::factory()->create();
        $this->team->addMember($teamAdmin, 'Team Admin');

        Livewire::actingAs($teamAdmin)
            ->test(MembersTable::class, ['team' => $this->team])
            ->assertTableActionVisible('remove', $this->member)
            ->assertTableActionHidden('remove', $this->coOwner)
            ->assertTableActionHidden('remove', $this->primary);

        Livewire::actingAs($this->primary)
            ->test(MembersTable::class, ['team' => $this->team])
            ->assertTableActionVisible('remove', $this->coOwner)
            ->callTableAction('remove', $this->coOwner);

        $this->assertFalse($this->team->fresh()->hasUser($this->coOwner));
    }

    public function test_an_owners_role_is_shielded_from_a_manage_members_holder(): void
    {
        // The co-owner holds a role, so changeRole would otherwise be offered.
        $this->team->syncMemberRoles($this->coOwner, ['Team Admin']);

        $teamAdmin = User::factory()->create();
        $this->team->addMember($teamAdmin, 'Team Admin'); // manage-members, not manage-owners

        Livewire::actingAs($teamAdmin)
            ->test(MembersTable::class, ['team' => $this->team])
            ->assertTableActionHidden('changeRole', $this->coOwner)   // an owner's role is protected
            ->assertTableActionVisible('changeRole', $this->member);  // a regular member's isn't

        // Manage-owners authority (here the primary owner) may change an owner's role.
        Livewire::actingAs($this->primary)
            ->test(MembersTable::class, ['team' => $this->team])
            ->assertTableActionVisible('changeRole', $this->coOwner);
    }

    public function test_the_members_table_labels_owners(): void
    {
        $this->actingAs($this->primary)
            ->get(route('team.members', ['team' => $this->team->slug]))
            ->assertOk()
            ->assertSee('Primary Owner')
            ->assertSee('Owner')
            ->assertSee('Member');
    }

    protected function tearDown(): void
    {
        app(TeamContext::class)->clear();

        parent::tearDown();
    }
}
