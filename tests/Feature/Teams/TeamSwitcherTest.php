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

    /**
     * A scroller clips sideways too, and the panel is wider than the sidebar, so in both
     * sidebars (drawer, then pinned) it sits above the scrolling nav (#53).
     */
    public function test_it_sits_above_the_scrolling_nav_so_its_panel_is_not_clipped(): void
    {
        config(['teams.creation' => 'admin-only']);
        $user = User::factory()->create();
        $team = Team::factory()->ownedBy($user)->create(['name' => 'Aspley']);
        Team::factory()->create(['name' => 'Beenleigh'])->addMember($user);

        $html = $this->actingAs($user)->get(team_route('team.dashboard', $team))->assertOk()->getContent();

        $switchers = $this->offsets($html, 'x-bind:aria-controls="$id(\'team-switcher-menu\')"');
        $navs = $this->offsets($html, '<nav class="flex-1');
        $this->assertCount(2, $switchers);
        $this->assertCount(2, $navs);
        $this->assertTrue($switchers[0] < $navs[0] && $navs[0] < $switchers[1] && $switchers[1] < $navs[1]);
    }

    /** @return list<int> */
    private function offsets(string $html, string $needle): array
    {
        preg_match_all('/'.preg_quote($needle, '/').'/', $html, $matches, PREG_OFFSET_CAPTURE);

        return array_column($matches[0], 1);
    }
}
