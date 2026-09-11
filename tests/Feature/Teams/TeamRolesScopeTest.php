<?php

namespace Tests\Feature\Teams;

use App\Enums\SystemPermission;
use App\Livewire\Admin\Roles\CreateRole;
use App\Livewire\Admin\Roles\ManageRoles;
use App\Models\Role;
use App\Models\User;
use App\Support\Theme\DaisyColor;
use Concise\Teams\Database\Seeders\TeamRolesSeeder;
use Concise\Teams\Enums\TeamPermission;
use Concise\Teams\Models\Team;
use Concise\Teams\TeamsServiceProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
