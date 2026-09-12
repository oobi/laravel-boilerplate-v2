<?php

namespace Tests\Feature\Teams;

use App\Models\User;
use Concise\Teams\Enums\TeamAbility;
use Concise\Teams\Enums\TeamPermission;
use Concise\Teams\Livewire\Team\ManageOwnership;
use Concise\Teams\Livewire\Team\MembersTable;
use Concise\Teams\Livewire\Team\Settings;
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
 * exactly like any member. The ownership acts live on Settings (ManageOwnership),
 * which the primary owner can always open whatever role they hold.
 */
class TeamOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private const TEAM_ADMIN = 'Team Admin';

    private User $primary;

    private User $coOwner;

    private User $member;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        Team::createRole(self::TEAM_ADMIN, [TeamPermission::MANAGE_MEMBERS]);
        Team::createRole('Member');

        // Unrelated accounts first, so user ids and team_user pivot ids diverge: a
        // member query that let the pivot's `id` shadow the user's once slipped
        // through because in a bare fixture the two happened to coincide.
        User::factory()->count(3)->create();

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
        $this->team->syncMemberRoles($this->coOwner, [self::TEAM_ADMIN]);
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

    public function test_the_primary_owner_may_delete_only_under_self_service_creation(): void
    {
        // Who creates, deletes: an admin-provisioned team is the platform's to remove.
        $this->assertTrue(Gate::forUser($this->primary)->allows(TeamAbility::DELETE, $this->team));

        config(['teams.creation' => 'admin-only']);

        $this->assertFalse(Gate::forUser($this->primary)->allows(TeamAbility::DELETE, $this->team));
        $this->assertTrue(Gate::forUser($this->primary)->allows(TeamAbility::TRANSFER_OWNERSHIP, $this->team), 'transfer is unaffected');
    }

    public function test_the_primary_owner_can_promote_and_demote_co_owners(): void
    {
        $component = Livewire::actingAs($this->primary)
            ->test(ManageOwnership::class, ['team' => $this->team])
            ->assertActionVisible('addCoOwner')
            ->callAction('addCoOwner', data: ['user_id' => $this->member->id])
            ->assertHasNoActionErrors();

        $this->assertTrue($this->team->isOwnedBy($this->member));

        // Someone who's already a co-owner isn't eligible to be added again — and the refusal says so.
        $component->callAction('addCoOwner', data: ['user_id' => $this->coOwner->id])
            ->assertHasActionErrors(['user_id' => [team_trans('ownership.already_co_owner', ['person' => $this->coOwner->name])]]);

        Livewire::actingAs($this->primary)
            ->test(ManageOwnership::class, ['team' => $this->team])
            ->callAction('removeCoOwner', arguments: ['user' => $this->member->id]);

        $this->assertFalse($this->team->isOwnedBy($this->member));
        $this->assertTrue($this->team->hasUser($this->member), 'demotion keeps membership');
        $this->assertTrue($this->team->isOwnedBy($this->coOwner), 'the other co-owner is untouched');
    }

    public function test_a_co_owner_cannot_reach_the_ownership_section(): void
    {
        $this->team->syncMemberRoles($this->coOwner, [self::TEAM_ADMIN]); // even with manage-members

        Livewire::actingAs($this->coOwner)
            ->test(ManageOwnership::class, ['team' => $this->team])
            ->assertForbidden();
    }

    public function test_the_primary_owner_cannot_be_demoted(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->team->revokeOwner($this->primary);
    }

    public function test_transferring_ownership_makes_the_previous_primary_a_co_owner_and_gives_the_successor_the_owner_role(): void
    {
        $this->assertSame('Member', $this->team->roleFor($this->member));

        Livewire::actingAs($this->primary)
            ->test(ManageOwnership::class, ['team' => $this->team])
            ->callAction('transferOwnership', data: ['user_id' => $this->member->id])
            ->assertHasNoActionErrors();

        $this->team->refresh();
        $this->assertTrue($this->team->isPrimaryOwner($this->member));
        $this->assertTrue($this->team->isOwnedBy($this->primary), 'previous primary stays a co-owner');
        $this->assertFalse($this->team->isPrimaryOwner($this->primary));

        // The person now responsible holds the owner role (replacing "Member" — one role per member);
        // the previous owner keeps theirs.
        $this->assertSame(self::TEAM_ADMIN, $this->team->roleFor($this->member));
        $this->assertTrue(Gate::forUser($this->member)->allows(TeamAbility::MANAGE_MEMBERS, $this->team));
        $this->assertSame(self::TEAM_ADMIN, $this->team->roleFor($this->primary));
    }

    public function test_ownership_cannot_be_transferred_to_a_suspended_member(): void
    {
        $this->team->suspendMember($this->member);

        // Not offered as a choice, and rejected as one — with the reason.
        Livewire::actingAs($this->primary)
            ->test(ManageOwnership::class, ['team' => $this->team])
            ->callAction('transferOwnership', data: ['user_id' => $this->member->id])
            ->assertHasActionErrors(['user_id' => [team_trans('ownership.is_suspended', ['person' => $this->member->name])]]);

        $this->assertTrue($this->team->fresh()->isPrimaryOwner($this->primary));
    }

    public function test_a_system_admin_can_transfer_ownership_without_being_a_member(): void
    {
        $admin = User::factory()->superAdmin()->create();

        Livewire::actingAs($admin)
            ->test(ManageOwnership::class, ['team' => $this->team])
            ->callAction('transferOwnership', data: ['user_id' => $this->member->id])
            ->assertHasNoActionErrors();

        $this->assertTrue($this->team->fresh()->isPrimaryOwner($this->member));
    }

    public function test_a_primary_owner_without_a_role_can_still_reach_settings_and_the_ownership_acts(): void
    {
        // The default role was renamed/deleted after the team was made: the owner holds nothing.
        $this->team->syncMemberRoles($this->primary, []);
        $this->assertNull($this->team->roleFor($this->primary));

        // No role → no roster; but Settings (where the ownership acts live) still opens, read-only.
        $this->assertFalse(Gate::forUser($this->primary)->allows(TeamAbility::VIEW_MEMBERS, $this->team));
        $this->assertTrue(Gate::forUser($this->primary)->allows(TeamAbility::VIEW_SETTINGS, $this->team));

        $this->actingAs($this->primary)
            ->get(route('team.settings', ['team' => $this->team->slug]))
            ->assertOk()
            ->assertSee(team_trans('ownership.transfer'));

        Livewire::actingAs($this->primary)
            ->test(Settings::class, ['team' => $this->team])
            ->call('save')
            ->assertForbidden();

        Livewire::actingAs($this->primary)
            ->test(ManageOwnership::class, ['team' => $this->team])
            ->assertActionVisible('transferOwnership')
            ->assertActionVisible('addCoOwner');
    }

    public function test_the_primary_owner_cannot_change_their_own_role_from_the_team_area(): void
    {
        $this->assertSame(self::TEAM_ADMIN, $this->team->roleFor($this->primary));

        Livewire::actingAs($this->primary)
            ->test(MembersTable::class, ['team' => $this->team])
            ->assertTableActionHidden('changeRole', $this->primary)
            ->assertTableActionVisible('changeRole', $this->member);

        // A system admin still can — that's how a locked-out owner gets fixed.
        Livewire::actingAs(User::factory()->superAdmin()->create())
            ->test(MembersTable::class, ['team' => $this->team])
            ->assertTableActionVisible('changeRole', $this->primary);
    }

    public function test_the_primary_owner_can_delete_the_team_from_settings(): void
    {
        Livewire::actingAs($this->primary)
            ->test(Settings::class, ['team' => $this->team])
            ->assertActionVisible('deleteTeam')
            ->callAction('deleteTeam')
            ->assertRedirect(route('team.index'));

        $this->assertSoftDeleted($this->team);
    }

    public function test_only_someone_who_can_demote_a_co_owner_may_remove_them(): void
    {
        $teamAdmin = User::factory()->create();
        $this->team->addMember($teamAdmin, self::TEAM_ADMIN);

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
        $this->team->syncMemberRoles($this->coOwner, [self::TEAM_ADMIN]);

        $teamAdmin = User::factory()->create();
        $this->team->addMember($teamAdmin, self::TEAM_ADMIN); // manage-members, not manage-owners

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
