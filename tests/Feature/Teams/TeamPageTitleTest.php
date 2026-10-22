<?php

declare(strict_types=1);

namespace Tests\Feature\Teams;

use App\Models\User;
use Concise\Teams\Enums\TeamPermission;
use Concise\Teams\Models\Team;
use Concise\Teams\Support\TeamLabels;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The teams tier's pages name themselves in the browser tab like core's do
 * (Tests\Feature\PageTitleTest, GitHub #30).
 */
class TeamPageTitleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Compares the raw <title>, so a value escaped twice (O&amp;#039;Brien) fails.
     */
    private function assertTitled(string $expected, string $url): void
    {
        preg_match('/<title>(.*?)<\/title>/s', $this->get($url)->assertOk()->getContent(), $matches);

        $this->assertSame(e($expected.' · '.config('app.name')), trim($matches[1] ?? ''), $url);
    }

    public function test_admin_team_pages_name_the_page_then_what_it_sits_under(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());
        $team = Team::factory()->create(['name' => "Bob's & Co"]);
        $teams = TeamLabels::plural();

        $this->assertTitled("Bob's & Co · {$teams}", route('teams.show', $team));
        $this->assertTitled(team_trans('nav.members')." · Bob's & Co · {$teams}", route('teams.members', $team));
        $this->assertTitled(team_trans('nav.settings')." · Bob's & Co · {$teams}", route('teams.settings', $team));
    }

    public function test_team_pages_name_the_page_then_the_team(): void
    {
        Team::createRole('Lead', TeamPermission::cases());
        $lead = User::factory()->create();
        $team = Team::factory()->create(['name' => "Bob's & Co"]);
        $team->addMember($lead, 'Lead');
        Team::factory()->create()->addMember($lead, 'Lead');
        $this->actingAs($lead);

        $this->assertTitled(team_trans('nav.dashboard')." · Bob's & Co", team_route('team.dashboard', $team));
        $this->assertTitled(team_trans('members.title')." · Bob's & Co", team_route('team.members', $team));
        $this->assertTitled(team_trans('nav.settings')." · Bob's & Co", team_route('team.settings', $team));
        $this->assertTitled(team_trans('select.title'), route('team.select'));
    }

    public function test_a_team_named_like_its_tab_keeps_its_name(): void
    {
        Team::createRole('Lead', TeamPermission::cases());
        $lead = User::factory()->create();
        $team = Team::factory()->create(['name' => team_trans('members.title')]);
        $team->addMember($lead, 'Lead');
        $this->actingAs($lead);

        $this->assertTitled(team_trans('members.title').' · '.team_trans('members.title'), team_route('team.members', $team));
    }

    public function test_onboarding_names_itself(): void
    {
        $this->actingAs(User::factory()->create());

        $this->assertTitled(team_trans('onboarding.title'), route('team.onboarding'));
    }
}
