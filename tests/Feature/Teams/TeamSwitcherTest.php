<?php

namespace Tests\Feature\Teams;

use App\Models\User;
use Concise\Teams\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The sidebar's team switcher: a real button when there's somewhere to go, a
 * plain label when there isn't (oobi #34).
 */
class TeamSwitcherTest extends TestCase
{
    use RefreshDatabase;

    public function test_with_somewhere_to_switch_to_it_is_a_button_that_says_whether_it_is_open(): void
    {
        config(['teams.creation' => 'admin-only']);
        $user = User::factory()->create();
        $team = Team::factory()->create(['name' => 'Aspley']);
        $other = Team::factory()->create(['name' => 'Beenleigh']);
        $team->addMember($user);
        $other->addMember($user);

        $this->actingAs($user)
            ->blade('<x-teams::team-switcher :team="$team" />', ['team' => $team])
            ->assertSee('<button', false)->assertSee('x-ref="trigger"', false)
            ->assertSee('aria-expanded="false"', false)
            ->assertSee('Beenleigh')
            ->assertDontSee('role="button"', false);
    }

    public function test_with_nowhere_to_switch_to_it_is_a_plain_label(): void
    {
        config(['teams.creation' => 'admin-only']);
        $user = User::factory()->create();
        $team = Team::factory()->create(['name' => 'Aspley']);
        $team->addMember($user);

        $this->actingAs($user)
            ->blade('<x-teams::team-switcher :team="$team" />', ['team' => $team])
            ->assertSee('Aspley')
            ->assertDontSee('<button', false)
            ->assertDontSee('aria-expanded', false);
    }
}
