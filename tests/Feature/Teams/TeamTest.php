<?php

namespace Tests\Feature\Teams;

use App\Models\Teams\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_belong_to_more_than_one_team(): void
    {
        $user = User::factory()->create();
        $teamA = Team::factory()->create();
        $teamB = Team::factory()->create();

        $user->teams()->attach([$teamA->id, $teamB->id]);

        $this->assertEqualsCanonicalizing(
            [$teamA->id, $teamB->id],
            $user->teams()->pluck('teams.id')->all(),
        );
        $this->assertTrue($user->belongsToTeam($teamA));
        $this->assertTrue($user->belongsToTeam($teamB));
    }

    public function test_a_team_exposes_its_owner_and_members(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $team = Team::factory()->ownedBy($owner)->create();
        $team->users()->attach($member);

        $this->assertTrue($team->owner->is($owner));
        $this->assertTrue($team->hasUser($member));
        $this->assertTrue($team->hasUser($owner));
    }

    public function test_owner_belongs_to_team_even_without_a_membership_row(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);

        $this->assertTrue($owner->belongsToTeam($team));
    }

    public function test_slug_is_generated_uniquely_from_the_name(): void
    {
        $first = Team::factory()->create(['name' => 'Acme Studio', 'slug' => null]);
        $second = Team::factory()->create(['name' => 'Acme Studio', 'slug' => null]);

        $this->assertSame('acme-studio', $first->slug);
        $this->assertSame('acme-studio-2', $second->slug);
    }

    public function test_switch_team_sets_the_current_team_for_a_member(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();
        $user->teams()->attach($team);

        $this->assertTrue($user->switchTeam($team));
        $this->assertTrue($user->fresh()->isCurrentTeam($team));
        $this->assertTrue($user->currentTeam->is($team));
    }

    public function test_switch_team_is_rejected_for_a_non_member(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();

        $this->assertFalse($user->switchTeam($team));
        $this->assertNull($user->fresh()->current_team_id);
    }

    public function test_current_team_id_is_nulled_when_the_team_is_deleted(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();
        $user->teams()->attach($team);
        $user->switchTeam($team);

        $team->forceDelete();

        $this->assertNull($user->fresh()->current_team_id);
    }
}
