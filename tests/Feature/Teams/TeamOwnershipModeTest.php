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

/**
 * The `teams.ownership` model (sovereign vs managed) — whether owner status
 * bypasses team permissions or authority comes only from roles — and the
 * protection that an owner's/co-owner's role can only be changed by the primary
 * owner. See ~dev/TEAMS_DOMAINS_SCOPE.md.
 */
class TeamOwnershipModeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // createRole first-or-creates the permission rows these tests reference.
        Team::createRole('Team Admin', [TeamPermission::MANAGE_MEMBERS, TeamPermission::UPDATE_TEAM, TeamPermission::MANAGE_DOMAINS]);
        Team::createRole('Member');
    }

    public function test_a_sovereign_owner_bypasses_team_permissions(): void
    {
        // ownership defaults to sovereign
        $owner = User::factory()->create();
        $team = Team::factory()->ownedBy($owner)->create();

        $this->assertTrue(Gate::forUser($owner)->allows(TeamAbility::UPDATE, $team));
        $this->assertTrue(Gate::forUser($owner)->allows(TeamAbility::MANAGE_DOMAINS, $team));
        $this->assertTrue(Gate::forUser($owner)->allows(TeamAbility::DELETE, $team));
    }

    public function test_a_managed_owner_gets_no_bypass_and_authority_comes_from_a_role(): void
    {
        config(['teams.ownership' => 'managed']);
        $owner = User::factory()->create();
        $team = Team::factory()->ownedBy($owner)->create();

        // No role → owner status carries no config power.
        $this->assertFalse(Gate::forUser($owner)->allows(TeamAbility::UPDATE, $team));
        $this->assertFalse(Gate::forUser($owner)->allows(TeamAbility::MANAGE_DOMAINS, $team));

        // Grant a role → power comes from it.
        $team->syncMemberRoles($owner, ['Team Admin']);

        $this->assertTrue(Gate::forUser($owner)->allows(TeamAbility::UPDATE, $team));
        $this->assertTrue(Gate::forUser($owner)->allows(TeamAbility::MANAGE_DOMAINS, $team));
    }

    public function test_a_managed_primary_owner_still_cannot_delete_or_transfer(): void
    {
        config(['teams.ownership' => 'managed']);
        $owner = User::factory()->create();
        $team = Team::factory()->ownedBy($owner)->create();
        $team->syncMemberRoles($owner, ['Team Admin']); // even fully permissioned

        $this->assertFalse(Gate::forUser($owner)->allows(TeamAbility::DELETE, $team));
        $this->assertFalse(Gate::forUser($owner)->allows(TeamAbility::TRANSFER_OWNERSHIP, $team));
    }

    public function test_a_manage_members_holder_cannot_change_an_owners_role(): void
    {
        // Managed mode: owners hold real roles, so changeRole would otherwise be
        // offered — the protection is what keeps a manage-members holder out.
        config(['teams.ownership' => 'managed']);
        $primary = User::factory()->create();
        $team = Team::factory()->ownedBy($primary)->create();

        $coOwner = User::factory()->create();
        $team->addMember($coOwner);
        $team->makeOwner($coOwner);

        $actor = User::factory()->create();
        $team->addMember($actor);
        $team->syncMemberRoles($actor, ['Team Admin']); // has manage members

        $plain = User::factory()->create();
        $team->addMember($plain, 'Member');

        Livewire::actingAs($actor)
            ->test(MembersTable::class, ['team' => $team])
            ->assertTableActionHidden('changeRole', $coOwner)   // an owner's role is protected
            ->assertTableActionVisible('changeRole', $plain);   // a plain member's is not
    }

    public function test_a_system_admin_can_change_a_co_owners_role(): void
    {
        // Managed mode: the co-owner holds a real role, so changeRole is offered —
        // and manage-owners authority (here a system admin; a managed team owner is
        // muzzled) is what's allowed to use it on an owner. The role protection
        // gates the action, it doesn't remove it for the authorised party.
        config(['teams.ownership' => 'managed']);
        $primary = User::factory()->create();
        $team = Team::factory()->ownedBy($primary)->create();

        $coOwner = User::factory()->create();
        $team->addMember($coOwner);
        $team->makeOwner($coOwner);

        Livewire::actingAs(User::factory()->superAdmin()->create())
            ->test(MembersTable::class, ['team' => $team])
            ->assertTableActionVisible('changeRole', $coOwner);
    }

    protected function tearDown(): void
    {
        app(TeamContext::class)->clear();

        parent::tearDown();
    }
}
