<?php

namespace Tests\Feature\Teams;

use App\Models\User;
use Concise\Teams\Actions\CreateTeam;
use Concise\Teams\Enums\TeamAbility;
use Concise\Teams\Livewire\Admin\Teams\ListTeams;
use Concise\Teams\Livewire\Team\Onboarding;
use Concise\Teams\Models\Team;
use Concise\Teams\Support\TeamContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\TestCase;

/** Self-service team creation (config `teams.creation` + `teams.max_teams_per_user`). */
class TeamCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_with_no_team_can_create_one_and_lands_on_its_dashboard(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(Onboarding::class)
            ->assertActionVisible('createTeam')
            ->callAction('createTeam', data: ['name' => 'Northwind'])
            ->assertHasNoActionErrors();

        $team = Team::query()->where('name', 'Northwind')->firstOrFail();

        $this->assertTrue($team->isPrimaryOwner($user), 'the creator owns it');
        $this->assertTrue($team->hasUser($user), 'and is its first member');
        $this->assertNotEmpty($team->slug);
        $this->assertSame($team->id, $user->fresh()->current_team_id);
    }

    public function test_the_onboarding_page_offers_creation_only_under_self_service(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('team.onboarding'))
            ->assertOk()
            ->assertSee('Create one to get started');

        config(['teams.creation' => 'admin-provisioned']);

        Livewire::actingAs($user)
            ->test(Onboarding::class)
            ->assertActionHidden('createTeam')
            ->assertSee('Ask an administrator');
    }

    public function test_creation_is_refused_at_the_write_boundary_when_the_mode_forbids_it(): void
    {
        config(['teams.creation' => 'invitation-only']);
        $user = User::factory()->create();

        $this->assertFalse(Gate::forUser($user)->allows(TeamAbility::CREATE, Team::class));

        $this->expectException(AuthorizationException::class);
        app(CreateTeam::class)('Northwind', $user);
    }

    public function test_the_limit_counts_teams_owned_not_teams_joined(): void
    {
        config(['teams.max_teams_per_user' => 1]);
        $user = User::factory()->create();

        // Belonging to other people's teams never uses up the allowance.
        Team::factory()->count(3)->create()->each(fn (Team $team) => $team->addMember($user));
        $this->assertTrue(Gate::forUser($user)->allows(TeamAbility::CREATE, Team::class));

        app(CreateTeam::class)('Mine', $user);

        $this->assertFalse(Gate::forUser($user)->allows(TeamAbility::CREATE, Team::class));

        Livewire::actingAs($user)
            ->test(Onboarding::class)
            ->assertActionHidden('createTeam');

        $this->expectException(AuthorizationException::class);
        app(CreateTeam::class)('One too many', $user);
    }

    public function test_an_unlimited_limit_is_the_default(): void
    {
        $user = User::factory()->create();
        $this->assertNull(Team::ownedLimit());

        Team::factory()->count(5)->ownedBy($user)->create();

        $this->assertTrue(Gate::forUser($user)->allows(TeamAbility::CREATE, Team::class));
    }

    public function test_a_deleted_team_frees_up_the_allowance(): void
    {
        config(['teams.max_teams_per_user' => 1]);
        $user = User::factory()->create();
        $team = Team::factory()->ownedBy($user)->create();

        $this->assertFalse(Gate::forUser($user)->allows(TeamAbility::CREATE, Team::class));

        $team->delete();

        $this->assertTrue(Gate::forUser($user)->allows(TeamAbility::CREATE, Team::class));
    }

    public function test_creation_is_throttled_per_user(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 10; $i++) {
            RateLimiter::increment('create-team:'.$user->id, 3600);
        }

        Livewire::actingAs($user)
            ->test(Onboarding::class)
            ->callAction('createTeam', data: ['name' => 'Northwind'])
            ->assertNotified();

        $this->assertDatabaseMissing('teams', ['name' => 'Northwind']);

        $this->expectException(ThrottleRequestsException::class);
        app(CreateTeam::class)('Northwind', $user);
    }

    public function test_a_system_admin_provisioning_a_team_is_not_subject_to_the_limit(): void
    {
        // Admin > Teams goes through the `manage teams` permission, not TeamPolicy::create.
        config(['teams.max_teams_per_user' => 1, 'teams.creation' => 'admin-provisioned']);
        $admin = User::factory()->superAdmin()->create();
        $owner = User::factory()->create();
        Team::factory()->ownedBy($owner)->create();

        Livewire::actingAs($admin)
            ->test(ListTeams::class)
            ->callAction('createTeam', data: ['name' => 'Second', 'user_id' => $owner->id, 'active' => true])
            ->assertHasNoActionErrors();

        $this->assertSame(2, $owner->ownedTeams()->count());
    }

    protected function tearDown(): void
    {
        app(TeamContext::class)->clear();

        parent::tearDown();
    }
}
