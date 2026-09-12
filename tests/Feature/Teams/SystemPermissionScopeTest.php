<?php

namespace Tests\Feature\Teams;

use App\Enums\SystemPermission;
use App\Models\User;
use Concise\Teams\Enums\TeamAbility;
use Concise\Teams\Enums\TeamPermission;
use Concise\Teams\Livewire\Team\MembersTable;
use Concise\Teams\Livewire\Team\PendingInvitations;
use Concise\Teams\Models\Team;
use Concise\Teams\Support\TeamContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A SystemPermission is application-wide: it answers the same inside a team
 * route (where spatie's current scope is the team) as outside. And a system
 * admin's authority over teams lives in TeamPolicy::before() — the one place —
 * so components check a single TeamAbility.
 */
class SystemPermissionScopeTest extends TestCase
{
    use RefreshDatabase;

    /** A non-super system admin: the actor for whom scope bugs are visible (a super admin bypasses spatie entirely). */
    private function systemAdmin(): User
    {
        return User::factory()
            ->withPermission(SystemPermission::ACCESS_ADMIN_PANEL, SystemPermission::MANAGE_TEAMS)
            ->create();
    }

    public function test_a_system_permission_answers_the_same_inside_and_outside_a_team_scope(): void
    {
        $admin = $this->systemAdmin();
        $team = Team::factory()->create();
        $team->addMember($admin);

        $this->assertTrue($admin->hasSystemPermission(SystemPermission::MANAGE_TEAMS));

        $inside = app(TeamContext::class)->run($team, fn (): array => [
            Gate::forUser($admin)->allows(SystemPermission::MANAGE_TEAMS->value),
            $admin->hasSystemPermission(SystemPermission::ACCESS_ADMIN_PANEL),
            $admin->canAccessAdmin(),
        ]);

        $this->assertSame([true, true, true], $inside);

        // And the pin leaves nothing behind: back at the system scope the answer is unchanged.
        $this->assertTrue(Gate::forUser($admin)->allows(SystemPermission::MANAGE_TEAMS->value));
    }

    public function test_the_admin_dashboard_link_survives_entering_a_team(): void
    {
        $admin = $this->systemAdmin();
        $team = Team::factory()->create();
        $team->addMember($admin);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(team_trans('nav.admin_dashboard'));

        $this->actingAs($admin->fresh())
            ->get(route('team.dashboard', ['team' => $team->slug]))
            ->assertOk()
            ->assertSee(team_trans('nav.admin_dashboard'));
    }

    public function test_a_team_permission_never_answers_as_a_system_permission(): void
    {
        // The pin only intercepts SystemPermission names; a team role's grant stays in its team.
        Team::createRole('Manager', [TeamPermission::MANAGE_MEMBERS]);
        $user = User::factory()->create();
        $team = Team::factory()->create();
        $team->addMember($user, 'Manager');

        $this->assertFalse(Gate::forUser($user)->allows(TeamPermission::MANAGE_MEMBERS->value), 'not held at the system scope');
        $this->assertFalse($user->hasSystemPermission(SystemPermission::MANAGE_TEAMS));
        $this->assertTrue(Gate::forUser($user)->allows(TeamAbility::MANAGE_MEMBERS, $team), 'held in its team');
    }

    public function test_a_system_admin_holds_every_team_ability_without_being_a_member(): void
    {
        $admin = $this->systemAdmin();
        $team = Team::factory()->create();
        $this->assertFalse($team->hasUser($admin));

        foreach (TeamAbility::cases() as $ability) {
            if ($ability === TeamAbility::CREATE) {
                continue; // class-level: self-service creation is its own rule
            }

            $this->assertTrue(Gate::forUser($admin)->allows($ability, $team), $ability->value);
        }

        // Still true from inside the team's own scope — the system permission is pinned there too.
        $this->assertTrue(app(TeamContext::class)->run($team, fn (): bool => Gate::forUser($admin)->allows(TeamAbility::MANAGE_OWNERS, $team)));
    }

    public function test_a_plain_system_user_holds_no_team_ability(): void
    {
        // `access admin panel` alone is not authority over teams.
        $support = User::factory()->withPermission(SystemPermission::ACCESS_ADMIN_PANEL)->create();
        $team = Team::factory()->create();

        $this->assertFalse(Gate::forUser($support)->allows(TeamAbility::VIEW_MEMBERS, $team));
        $this->assertFalse(Gate::forUser($support)->allows(TeamAbility::DELETE, $team));
    }

    public function test_the_members_table_offers_a_system_admin_its_actions_inside_the_team_area(): void
    {
        Team::createRole('Member', [TeamPermission::VIEW_MEMBERS]);
        $admin = $this->systemAdmin();
        $team = Team::factory()->create();
        $team->addMember($admin, 'Member'); // a plain member by role; authority comes from `manage teams`
        $other = User::factory()->create();
        $team->addMember($other, 'Member');

        // The team area sets the team scope for the whole request; the admin's system permission must still count.
        app(TeamContext::class)->set($team);

        Livewire::actingAs($admin)
            ->test(MembersTable::class, ['team' => $team])
            ->assertActionVisible('addMember')
            ->assertTableActionVisible('changeRole', $other)
            ->assertTableActionVisible('changeRole', $admin); // a system admin may act on their own row
    }

    public function test_a_system_admins_invitations_follow_the_admin_switch_not_the_member_one(): void
    {
        // TeamPolicy::before grants `invite` to admins, but which switch governs
        // them is decided by who they are, so admins-off must still refuse.
        config(['teams.invitations.admins' => false, 'teams.invitations.members' => true]);
        $admin = $this->systemAdmin();
        $team = Team::factory()->create();

        Livewire::actingAs($admin)
            ->test(PendingInvitations::class, ['team' => $team])
            ->assertForbidden();

        config(['teams.invitations.admins' => true]);

        Livewire::actingAs($admin)
            ->test(PendingInvitations::class, ['team' => $team])
            ->assertSuccessful()
            ->assertActionVisible('invite');
    }

    protected function tearDown(): void
    {
        app(TeamContext::class)->clear();

        parent::tearDown();
    }
}
