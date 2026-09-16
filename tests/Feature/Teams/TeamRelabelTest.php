<?php

namespace Tests\Feature\Teams;

use App\Livewire\Admin\Roles\ManageRoles;
use App\Models\User;
use Concise\Teams\Enums\TeamPermission;
use Concise\Teams\Models\Team;
use Concise\Teams\Models\TeamInvitation;
use Concise\Teams\Notifications\TeamInvitationNotification;
use Concise\Teams\Support\TeamContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * R6 / scope §8: `config('teams.labels')` relabels the whole tier — every
 * render-time string, the admin breadcrumb, the Roles screen tab — with no
 * class or table renamed. (The sidebar nav label is read once at boot from
 * the same config, so it's exercised by TEAMS_LABEL_PLURAL rather than here.)
 */
class TeamRelabelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['teams.labels.team.singular' => 'Salon', 'teams.labels.team.plural' => 'Salons']);
        Team::createRole('Salon Admin', [TeamPermission::MANAGE_MEMBERS]);
    }

    public function test_no_string_puts_an_article_before_a_label_placeholder(): void
    {
        // A label is substituted verbatim, so "a :team" reads "a organisation" on
        // a relabelled install and "Add a :member" reads "Add a staff". Articles
        // must be reworded around the placeholder, never written in front of one.
        // ("every :team", "this :team", "any :team" are invariant and fine.)
        $offenders = [];
        $strings = (array) __('teams::teams');

        array_walk_recursive(
            $strings,
            function (string $line, string $key) use (&$offenders): void {
                if (preg_match('/\b(an?)\s+(:[A-Za-z]+)/i', $line, $match)) {
                    $offenders[] = "{$key}: \"{$match[1]} {$match[2]}\"";
                }
            },
        );

        $this->assertSame([], $offenders, 'Reword these around the placeholder: '.implode('; ', $offenders));
    }

    public function test_the_admin_team_pages_use_the_configured_word(): void
    {
        $team = Team::factory()->create(['name' => 'Chez Claude']);

        $this->actingAs(User::factory()->superAdmin()->create())
            ->get(route('teams.show', $team))
            ->assertOk()
            ->assertSee('View and manage salon information')
            ->assertSee('Back to Salons')
            ->assertSee('Salon information')
            ->assertSee('Salons') // the breadcrumb parent
            ->assertDontSee('Team information');
    }

    public function test_the_team_area_uses_the_configured_word(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->ownedBy($owner)->create();

        $this->actingAs($owner)
            ->get(route('team.dashboard', ['team' => $team->slug]))
            ->assertOk()
            ->assertSee('Salon dashboard')
            ->assertSee('This salon’s content will live here.');

        $this->actingAs(User::factory()->create())
            ->get(route('team.onboarding'))
            ->assertOk()
            ->assertSee('You’re not part of any salon yet');
    }

    public function test_the_roles_screen_and_permission_vocabulary_use_the_configured_word(): void
    {
        Livewire::actingAs(User::factory()->superAdmin()->create())
            ->withQueryParams(['scope' => Team::ROLE_SCOPE])
            ->test(ManageRoles::class)
            ->assertSee('Define what members can do')
            ->assertSee('Update Salon Settings')
            ->assertSee('Salon Settings');
    }

    public function test_the_member_and_owner_words_are_configurable(): void
    {
        config([
            'teams.labels.member.singular' => 'Stylist', 'teams.labels.member.plural' => 'Stylists',
            'teams.labels.owner.singular' => 'Manager', 'teams.labels.owner.plural' => 'Managers',
        ]);
        $team = Team::factory()->create(['name' => 'Chez Claude']);

        // The admin overview's stat labels come straight from the member/owner words.
        $this->actingAs(User::factory()->superAdmin()->create())
            ->get(route('teams.show', $team))
            ->assertOk()
            ->assertSee('Stylists')
            ->assertSee('Managers');

        // …and so does the owner badge on the team's own members page.
        $owner = User::factory()->create();
        $team = Team::factory()->ownedBy($owner)->create();

        $this->actingAs($owner)
            ->get(route('team.members', ['team' => $team->slug]))
            ->assertOk()
            ->assertSee('stylists')          // the ":members" word in the page description
            ->assertSee('Primary Manager');  // the ":Owner" badge
    }

    public function test_the_user_page_and_the_email_use_the_configured_word(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->ownedBy($user)->create(['name' => 'Chez Claude']);

        $this->actingAs(User::factory()->superAdmin()->create())
            ->get(route('users.show', $user))
            ->assertOk()
            ->assertSee('Salon memberships');

        $invitation = TeamInvitation::factory()->create(['team_id' => $team->id]);
        $mail = (new TeamInvitationNotification($invitation))->toMail(new AnonymousNotifiable);

        $this->assertStringContainsString('Chez Claude salon', implode(' ', $mail->introLines));
    }

    protected function tearDown(): void
    {
        app(TeamContext::class)->clear();

        parent::tearDown();
    }
}
