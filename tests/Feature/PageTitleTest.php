<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Support\PageTitle;
use Concise\Teams\Enums\TeamPermission;
use Concise\Teams\Models\Team;
use Concise\Teams\Support\TeamLabels;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every page names itself in the browser tab, "Page · App name", so tabs,
 * history, bookmarks and screen readers can tell them apart (WCAG 2.4.2,
 * GitHub #30).
 */
class PageTitleTest extends TestCase
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

    public function test_admin_pages_name_the_page_then_what_it_sits_under(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());
        $user = User::factory()->create(['first_name' => 'Siobhan', 'last_name' => "O'Brien & Co"]);
        $team = Team::factory()->create(['name' => "Bob's & Co"]);
        $teams = TeamLabels::plural();

        $this->assertTitled(__('admin.users'), route('users.index'));
        $this->assertTitled(__('admin.create').' · '.__('admin.users'), route('users.create'));
        $this->assertTitled("Siobhan O'Brien & Co · ".__('admin.users'), route('users.show', $user));
        $this->assertTitled(__('admin.edit')." · Siobhan O'Brien & Co · ".__('admin.users'), route('users.edit', $user));
        $this->assertTitled(__('admin.roles'), route('roles.index'));
        $this->assertTitled('Lead · '.__('admin.roles'), route('roles.edit', Role::create(['name' => 'Lead'])));
        $this->assertTitled("Bob's & Co · {$teams}", route('teams.show', $team));
        $this->assertTitled(team_trans('nav.members')." · Bob's & Co · {$teams}", route('teams.members', $team));
        $this->assertTitled(team_trans('nav.settings')." · Bob's & Co · {$teams}", route('teams.settings', $team));
        $this->assertTitled(__('admin.password').' · '.__('admin.my_profile'), route('profile.password'));
    }

    public function test_an_edited_record_is_a_linked_breadcrumb(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());
        $user = User::factory()->create(['first_name' => 'Siobhan', 'last_name' => "O'Brien"]);

        $this->get(route('users.edit', $user))
            ->assertSeeHtml('href="'.route('users.show', $user).'"')
            ->assertSee("Siobhan O'Brien");
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

    public function test_sign_in_pages_name_themselves(): void
    {
        $this->assertTitled(__('Log in'), route('login'));
        $this->assertTitled(__('Forgot your password?'), route('password.request'));
    }

    public function test_error_pages_name_the_error(): void
    {
        preg_match('/<title>(.*?)<\/title>/s', $this->get('/no-such-page')->assertNotFound()->getContent(), $matches);

        $this->assertSame(__('Page not found').' · '.config('app.name'), $matches[1] ?? '');
    }

    public function test_a_page_without_a_title_falls_back_to_the_app_name(): void
    {
        $this->assertSame(config('app.name'), PageTitle::for());
        $this->assertSame('Users · '.config('app.name'), PageTitle::for('Users', [null, '']));
    }
}
