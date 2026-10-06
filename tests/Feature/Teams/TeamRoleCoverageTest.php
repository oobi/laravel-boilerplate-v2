<?php

namespace Tests\Feature\Teams;

use App\Enums\SystemPermission;
use App\Livewire\Admin\Roles\CreateRole;
use App\Livewire\Admin\Roles\ManageRoles;
use App\Models\User;
use Concise\Teams\Actions\InviteMember;
use Concise\Teams\Enums\TeamPermission;
use Concise\Teams\Livewire\Team\MembersTable;
use Concise\Teams\Livewire\Team\PendingInvitations;
use Concise\Teams\Models\Team;
use Concise\Teams\Models\TeamInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Team roles follow the same rule as head office roles (#82): someone who can
 * manage members gives, takes away or acts on only what they hold all of in
 * the team. Head office running teams covers everything.
 */
class TeamRoleCoverageTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;

    private User $lead;

    private User $manager;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();

        Team::createRole('Lead', [TeamPermission::MANAGE_MEMBERS]);
        Team::createRole('Manager', [TeamPermission::MANAGE_MEMBERS, TeamPermission::UPDATE_TEAM]);
        Team::createRole('Member');

        $this->team = Team::factory()->create();
        $this->lead = $this->joined('Lead');
        $this->manager = $this->joined('Manager');
        $this->member = $this->joined('Member');
    }

    private function joined(string $role): User
    {
        $user = User::factory()->create();
        $this->team->addMember($user, $role);

        return $user;
    }

    /** @return array{roles: string|list<string>} */
    private function roles(string $role): array
    {
        return ['roles' => Team::allowsMultipleRoles() ? [$role] : $role];
    }

    public function test_a_member_manager_gives_only_roles_they_hold_everything_of(): void
    {
        Livewire::actingAs($this->lead)
            ->test(MembersTable::class, ['team' => $this->team])
            ->callTableAction('changeRole', $this->member, $this->roles('Manager'))
            ->assertHasFormErrors();

        $this->assertSame('Member', $this->team->roleFor($this->member));

        Livewire::actingAs($this->lead)
            ->test(MembersTable::class, ['team' => $this->team])
            ->callTableAction('changeRole', $this->member, $this->roles('Lead'))
            ->assertHasNoFormErrors();

        $this->assertSame('Lead', $this->team->fresh()->roleFor($this->member->fresh()));
    }

    public function test_nobody_acts_on_a_member_with_more_access_but_peers_act_on_each_other(): void
    {
        $peer = $this->joined('Lead');

        Livewire::actingAs($this->lead)
            ->test(MembersTable::class, ['team' => $this->team])
            ->assertTableActionHidden('changeRole', $this->manager)
            ->assertTableActionHidden('suspend', $this->manager)
            ->assertTableActionHidden('remove', $this->manager)
            ->assertTableActionVisible('changeRole', $peer)
            ->assertTableActionVisible('suspend', $peer);
    }

    public function test_head_office_running_teams_gives_any_role(): void
    {
        Livewire::actingAs(User::factory()->withPermission(SystemPermission::MANAGE_TEAMS)->create())
            ->test(MembersTable::class, ['team' => $this->team])
            ->callTableAction('changeRole', $this->member, $this->roles('Manager'))
            ->assertHasNoFormErrors();

        $this->assertSame('Manager', $this->team->fresh()->roleFor($this->member->fresh()));
    }

    public function test_a_role_editor_without_manage_teams_cant_rewrite_their_own_team_role(): void
    {
        $this->member->givePermissionTo(Permission::findOrCreate(SystemPermission::MANAGE_ROLES->value));
        $memberRole = Team::availableRoles()->where('name', 'Member')->firstOrFail();

        Livewire::actingAs($this->member)
            ->test(ManageRoles::class, ['role' => $memberRole])
            ->assertForbidden();

        Livewire::withQueryParams(['scope' => Team::ROLE_SCOPE])
            ->actingAs($this->member)
            ->test(CreateRole::class)
            ->assertForbidden();

        $this->member->givePermissionTo(Permission::findOrCreate(SystemPermission::MANAGE_TEAMS->value));

        $this->assertTrue(Livewire::actingAs($this->member->fresh())->test(ManageRoles::class, ['role' => $memberRole])->instance()->canEditRole());
    }

    public function test_an_invitation_offers_only_a_role_the_inviter_holds_everything_of(): void
    {
        Mail::fake();
        config(['teams.invitations.members' => true]);

        app(InviteMember::class)($this->team, 'new@example.com', 'Lead', $this->lead);
        $this->assertTrue($this->team->invitations()->where('email', 'new@example.com')->exists());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(team_trans('invitations.role_not_allowed', ['role' => 'Manager']));
        app(InviteMember::class)($this->team, 'other@example.com', 'Manager', $this->lead);
    }

    public function test_the_members_table_checks_coverage_without_a_query_per_member(): void
    {
        // Each count starts cold (fresh objects, so nothing remembered), paying the same one-off lookups.
        $queries = function (): int {
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            DB::flushQueryLog();
            DB::enableQueryLog();
            Livewire::actingAs($this->lead->fresh())->test(MembersTable::class, ['team' => $this->team->fresh()]);

            return count(DB::getQueryLog());
        };

        $few = $queries();
        collect(range(1, 8))->each(fn () => $this->joined('Member'));

        $this->assertSame($few, $queries());
    }

    public function test_inviting_with_a_role_you_dont_cover_is_a_form_error_not_a_crash(): void
    {
        Notification::fake();
        config(['teams.invitations.members' => true]);
        Team::createRole('Recruiter', [TeamPermission::INVITE_MEMBERS]);
        $recruiter = $this->joined('Recruiter');

        Livewire::actingAs($recruiter)
            ->test(PendingInvitations::class, ['team' => $this->team])
            ->callAction('invite', data: ['email' => 'new@example.com', 'role' => 'Manager'])
            ->assertHasFormErrors(['role' => team_trans('invitations.role_not_allowed', ['role' => 'Manager'])]);

        $this->assertFalse($this->team->invitations()->exists());
    }

    public function test_anything_invite_member_refuses_past_the_form_is_shown_not_thrown(): void
    {
        Notification::fake();
        config(['teams.invitations.members' => true]);
        Team::createRole('Recruiter', [TeamPermission::INVITE_MEMBERS]);
        $recruiter = $this->joined('Recruiter');

        // The form and the action disagreeing (say, a role changed between showing the form and sending it).
        $this->app->instance(InviteMember::class, new class extends InviteMember
        {
            public function __invoke(Team $team, string $email, ?string $role = null, ?User $inviter = null): TeamInvitation
            {
                throw new InvalidArgumentException(team_trans('invitations.role_not_allowed', ['role' => 'Lead']));
            }
        });

        Livewire::actingAs($recruiter)
            ->test(PendingInvitations::class, ['team' => $this->team])
            ->callAction('invite', data: ['email' => 'new@example.com'])
            ->assertNotified(team_trans('invitations.role_not_allowed', ['role' => 'Lead']));

        $this->assertFalse($this->team->invitations()->exists());
    }
}
