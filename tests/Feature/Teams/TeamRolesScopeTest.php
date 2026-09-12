<?php

namespace Tests\Feature\Teams;

use App\Enums\SystemPermission;
use App\Livewire\Admin\Roles\CreateRole;
use App\Livewire\Admin\Roles\ManageRoles;
use App\Models\Role;
use App\Models\User;
use App\Support\Theme\DaisyColor;
use Concise\Teams\Database\Seeders\TeamRolesSeeder;
use Concise\Teams\Enums\TeamAbility;
use Concise\Teams\Enums\TeamPermission;
use Concise\Teams\Models\Team;
use Concise\Teams\TeamsServiceProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

/**
 * Team roles are centrally defined and scoped apart from system roles, so the
 * two schemes never bleed into each other (~dev/TEAMS_TIER_SCOPE.md §5). The
 * seeder tests below invoke TeamRolesSeeder directly because it is the unit
 * under test — not as fixture setup (see tests.md).
 */
class TeamRolesScopeTest extends TestCase
{
    use RefreshDatabase;

    private function runSeeder(): void
    {
        (new TeamRolesSeeder)->run();
    }

    public function test_the_seeder_creates_the_default_roles_into_an_empty_set(): void
    {
        $this->runSeeder();

        $this->assertSame(2, Team::availableRoles()->count());

        $admin = Team::availableRoles()->where('name', 'Team Admin')->firstOrFail();
        $this->assertNull($admin->team_id, 'shared team roles resolve in every team scope');
        $this->assertSame(Team::ROLE_SCOPE, $admin->scope);
        $this->assertTrue($admin->hasPermissionTo(TeamPermission::MANAGE_MEMBERS->value));
        $this->assertSame(DaisyColor::ERROR, $admin->color);

        $member = Team::availableRoles()->where('name', 'Member')->firstOrFail();
        $this->assertFalse($member->hasPermissionTo(TeamPermission::MANAGE_MEMBERS->value));
        $this->assertTrue($member->hasPermissionTo(TeamPermission::VIEW_MEMBERS->value), 'members see the roster by default');
        $this->assertSame(DaisyColor::PRIMARY, $member->color);
    }

    public function test_the_seeder_does_nothing_when_the_teams_tier_is_not_active(): void
    {
        // Simulate the package being present but never booted (e.g. discovery
        // disabled). Application offers no unregister, so forget it directly.
        (function (): void {
            unset($this->loadedProviders[TeamsServiceProvider::class]);
        })->call($this->app);
        $this->assertFalse(TeamsServiceProvider::isActive());

        $this->runSeeder();

        $this->assertSame(0, Team::availableRoles()->count());
        $this->assertDatabaseMissing('permissions', ['name' => TeamPermission::MANAGE_MEMBERS->value]);
    }

    public function test_the_seeder_works_with_model_events_suppressed(): void
    {
        // Seeders commonly run under WithoutModelEvents. Spatie flushes its
        // permission cache from a `saved` event, so with events off the cache
        // goes stale mid-seed — createRole must not depend on it (regression:
        // "Duplicate entry 'manage team members-web'" on demo:reset).
        Model::withoutEvents(fn () => $this->runSeeder());

        $admin = Team::availableRoles()->where('name', 'Team Admin')->firstOrFail();
        $this->assertTrue($admin->hasPermissionTo(TeamPermission::MANAGE_MEMBERS->value));
    }

    public function test_the_seeder_is_idempotent(): void
    {
        $this->runSeeder();
        $this->runSeeder();

        $this->assertSame(2, Team::availableRoles()->count());
    }

    public function test_a_deleted_default_stays_deleted(): void
    {
        $this->runSeeder();
        Team::availableRoles()->where('name', 'Member')->firstOrFail()->delete();

        $this->runSeeder(); // admins own the set now — nothing resurrects

        $this->assertFalse(Team::availableRoles()->where('name', 'Member')->exists());
        $this->assertSame(1, Team::availableRoles()->count());
    }

    public function test_a_renamed_default_is_not_resurrected(): void
    {
        $this->runSeeder();
        Team::availableRoles()->where('name', 'Team Admin')->firstOrFail()->update(['name' => 'Manager']);

        $this->runSeeder();

        $this->assertFalse(Team::availableRoles()->where('name', 'Team Admin')->exists());
        $this->assertSame(2, Team::availableRoles()->count());
    }

    public function test_creating_a_team_role_fails_clearly_when_the_name_is_taken(): void
    {
        Role::findOrCreate('Member'); // a system role called "Member"

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Cannot create team role 'Member'");

        Team::createRole('Member');
    }

    public function test_system_roles_are_scoped_system_by_default(): void
    {
        $role = Role::findOrCreate('Support');

        $this->assertSame(Role::SYSTEM_SCOPE, $role->fresh()->scope);
    }

    public function test_role_names_are_globally_unique_at_the_database_level(): void
    {
        Role::findOrCreate('Support'); // a system role

        $this->expectException(UniqueConstraintViolationException::class);

        // Same name in the team scope — must be refused by the DB, not just the form.
        Role::query()->create([
            'name' => 'Support',
            'guard_name' => 'web',
            'team_id' => null,
            'scope' => Team::ROLE_SCOPE,
        ]);
    }

    public function test_the_system_roles_screen_does_not_list_team_roles(): void
    {
        Team::createRole('Team Admin');
        Role::findOrCreate('Support');

        Livewire::actingAs(User::factory()->superAdmin()->create())
            ->test(ManageRoles::class)
            ->assertSee('Support')
            ->assertDontSee('Team Admin');
    }

    public function test_the_roles_screen_shows_a_team_tab_that_opens_on_the_first_team_role(): void
    {
        Team::createRole('Team Admin');
        $member = Team::createRole('Member');
        Role::findOrCreate('Support');

        Livewire::actingAs(User::factory()->superAdmin()->create())
            ->withQueryParams(['scope' => Team::ROLE_SCOPE])
            ->test(ManageRoles::class)
            ->assertSet('scopeKey', Team::ROLE_SCOPE)
            ->assertSet('selectedRoleId', (string) $member->id)
            ->assertSeeHtml('role="tab"')
            ->assertSee(config('teams.labels.team.singular'))
            ->assertDontSee('Support');
    }

    public function test_a_team_role_is_edited_with_the_team_vocabulary(): void
    {
        $teamRole = Team::createRole('Team Admin');

        Livewire::actingAs(User::factory()->superAdmin()->create())
            ->test(ManageRoles::class, ['role' => $teamRole])
            ->assertSet('scopeKey', Team::ROLE_SCOPE)
            ->assertSee(TeamPermission::MANAGE_MEMBERS->label())
            ->assertDontSee(SystemPermission::MANAGE_USERS->label())
            ->set('data.permissions_members', [TeamPermission::MANAGE_MEMBERS->value])
            ->call('save')
            ->assertHasNoErrors();

        $teamRole->refresh();
        $this->assertTrue($teamRole->checkPermissionTo(TeamPermission::MANAGE_MEMBERS->value));
        $this->assertFalse($teamRole->checkPermissionTo(TeamPermission::INVITE_MEMBERS->value));
    }

    public function test_saving_a_manage_permission_auto_grants_its_view_counterpart(): void
    {
        $role = Team::createRole('Manager');

        Livewire::actingAs(User::factory()->superAdmin()->create())
            ->test(ManageRoles::class, ['role' => $role])
            ->set('data.permissions_members', [TeamPermission::MANAGE_MEMBERS->value])
            ->set('data.permissions_team_settings', [TeamPermission::UPDATE_TEAM->value])
            ->call('save')
            ->assertHasNoErrors();

        $role->refresh();
        // Manage/Update imply their View counterpart — stored, not just displayed.
        $this->assertTrue($role->checkPermissionTo(TeamPermission::VIEW_MEMBERS->value));
        $this->assertTrue($role->checkPermissionTo(TeamPermission::VIEW_SETTINGS->value));
    }

    public function test_the_roles_form_ticks_an_implied_permission_with_its_implier_and_keeps_it_while_ticked(): void
    {
        config(['teams.domains.enabled' => true]);
        $role = Team::createRole('Manager');

        $component = Livewire::actingAs(User::factory()->superAdmin()->create())
            ->test(ManageRoles::class, ['role' => $role]);

        // Ticking Update ticks the View it carries.
        $component->set('data.permissions_team_settings', [TeamPermission::UPDATE_TEAM->value])
            ->assertSet('data.permissions_team_settings', [
                TeamPermission::VIEW_SETTINGS->value,
                TeamPermission::UPDATE_TEAM->value,
            ]);

        // The View option is disabled on screen while Update is on; a state that
        // arrives without it (bypassing the control) is completed, never honoured.
        $component->set('data.permissions_team_settings', [
            TeamPermission::UPDATE_TEAM->value,
            TeamPermission::MANAGE_DOMAINS->value,
        ])->assertSet('data.permissions_team_settings', [
            TeamPermission::VIEW_SETTINGS->value,
            TeamPermission::UPDATE_TEAM->value,
            TeamPermission::MANAGE_DOMAINS->value,
        ]);

        // Unticking the impliers frees View: it stays ticked (nothing is silently
        // removed) and can now be unticked on its own.
        $component->set('data.permissions_team_settings', [TeamPermission::VIEW_SETTINGS->value])
            ->assertSet('data.permissions_team_settings', [TeamPermission::VIEW_SETTINGS->value])
            ->set('data.permissions_team_settings', [])
            ->assertSet('data.permissions_team_settings', []);
    }

    public function test_a_manage_permission_carries_its_view_counterpart_at_check_time(): void
    {
        // Stored minimally (as the seeder does), resolved with the implication —
        // the one place it's enforced, so form-made and seeded roles agree.
        Team::createRole('Manager', [TeamPermission::MANAGE_MEMBERS, TeamPermission::UPDATE_TEAM]);
        $team = Team::factory()->create();
        $manager = User::factory()->create();
        $team->addMember($manager, 'Manager');

        $this->assertTrue(Gate::forUser($manager)->allows(TeamAbility::VIEW_MEMBERS, $team));
        $this->assertTrue(Gate::forUser($manager)->allows(TeamAbility::VIEW_SETTINGS, $team));
        $this->assertFalse(Gate::forUser($manager)->allows(TeamAbility::INVITE, $team), 'implications are single-level and specific');
    }

    public function test_a_feature_gated_permission_is_not_offered_but_is_kept_on_save_while_its_feature_is_off(): void
    {
        // Granted while domains were on, then the feature switched off in this environment.
        config(['teams.domains.enabled' => false]);
        $role = Team::createRole('Manager', [TeamPermission::MANAGE_DOMAINS, TeamPermission::UPDATE_TEAM]);

        Livewire::actingAs(User::factory()->superAdmin()->create())
            ->test(ManageRoles::class, ['role' => $role])
            // Not on the screen…
            ->assertDontSee(TeamPermission::MANAGE_DOMAINS->label())
            // …and select-all can't reach it either…
            ->set('data.permissions_team_settings_select_all', true)
            ->assertSet('data.permissions_team_settings', [
                TeamPermission::VIEW_SETTINGS->value,
                TeamPermission::UPDATE_TEAM->value,
            ])
            // …yet a save keeps what it couldn't show.
            ->call('save')
            ->assertHasNoErrors();

        $role->refresh()->unsetRelation('permissions');
        $this->assertTrue($role->checkPermissionTo(TeamPermission::MANAGE_DOMAINS->value));
        $this->assertTrue($role->checkPermissionTo(TeamPermission::UPDATE_TEAM->value));

        // With the feature on it's an ordinary option again.
        config(['teams.domains.enabled' => true]);

        Livewire::actingAs(User::factory()->superAdmin()->create())
            ->test(ManageRoles::class, ['role' => $role])
            ->assertSee(TeamPermission::MANAGE_DOMAINS->label())
            ->assertSet('data.permissions_team_settings', [
                TeamPermission::VIEW_SETTINGS->value,
                TeamPermission::UPDATE_TEAM->value,
                TeamPermission::MANAGE_DOMAINS->value,
            ]);
    }

    public function test_the_seeder_grants_manage_domains_only_while_the_feature_is_on(): void
    {
        config(['teams.domains.enabled' => false]);
        $this->runSeeder();

        $admin = Team::availableRoles()->where('name', 'Team Admin')->firstOrFail();
        $this->assertFalse($admin->hasPermissionTo(TeamPermission::MANAGE_DOMAINS->value), 'a seeder sets up the base scenario, not a switched-off feature');
        $this->assertTrue($admin->hasPermissionTo(TeamPermission::UPDATE_TEAM->value));

        Team::availableRoles()->get()->each->delete();
        config(['teams.domains.enabled' => true]);
        $this->runSeeder();

        $admin = Team::availableRoles()->where('name', 'Team Admin')->firstOrFail();
        $this->assertTrue($admin->hasPermissionTo(TeamPermission::MANAGE_DOMAINS->value));
    }

    public function test_a_team_role_can_be_created_from_the_team_tab(): void
    {
        Livewire::actingAs(User::factory()->superAdmin()->create())
            ->withQueryParams(['scope' => Team::ROLE_SCOPE])
            ->test(CreateRole::class)
            ->assertSee(TeamPermission::INVITE_MEMBERS->label())
            ->assertDontSee(SystemPermission::MANAGE_USERS->label())
            ->set('data.name', 'Billing')
            ->set('data.permissions_members', [TeamPermission::INVITE_MEMBERS->value])
            ->call('create')
            ->assertHasNoErrors();

        $role = Team::availableRoles()->where('name', 'Billing')->firstOrFail();
        $this->assertNull($role->team_id, 'shared team roles resolve in every team scope');
        $this->assertTrue($role->checkPermissionTo(TeamPermission::INVITE_MEMBERS->value));
    }
}
