<?php

namespace Tests\Feature\Teams;

use App\Models\User;
use Concise\Teams\Enums\TeamPermission;
use Concise\Teams\Livewire\Team\Settings;
use Concise\Teams\Models\Team;
use Concise\Teams\Support\TeamContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The team-area Settings page: who may open it (an owner, the dedicated
 * VIEW_SETTINGS permission, or a section editor) and that viewing is a separate
 * permission from editing — a view-only holder gets a read-only form and the
 * save is refused server-side, not just hidden.
 */
class TeamSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed the permission rows the policy's checkPermissionTo resolves against
        // (both the view and the update permission, so either check is answerable),
        // and make it the role a new team's owner is given at setup so owners reach
        // Settings through a role (ownership grants no visibility of its own).
        Team::createRole('Settings Admin', [
            TeamPermission::VIEW_SETTINGS,
            TeamPermission::UPDATE_TEAM,
        ]);

        config(['teams.default_owner_role' => 'Settings Admin']);
    }

    public function test_the_page_is_open_to_an_owner_even_with_domains_off(): void
    {
        // The page also hosts team details, so it exists independent of the overlay.
        $owner = User::factory()->create();
        $team = Team::factory()->ownedBy($owner)->create();

        $this->actingAs($owner)
            ->get(route('team.settings', ['team' => $team->slug]))
            ->assertOk();
    }

    public function test_the_page_is_forbidden_to_a_plain_member(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->ownedBy($owner)->create();
        $member = User::factory()->create();
        $team->addMember($member);

        $this->actingAs($member)
            ->get(route('team.settings', ['team' => $team->slug]))
            ->assertForbidden();
    }

    public function test_a_view_settings_holder_opens_the_page_but_cannot_save(): void
    {
        Team::createRole('Viewer', [TeamPermission::VIEW_SETTINGS]);
        $owner = User::factory()->create();
        $team = Team::factory()->ownedBy($owner)->create();
        $viewer = User::factory()->create();
        $team->addMember($viewer, 'Viewer');

        $this->actingAs($viewer)
            ->get(route('team.settings', ['team' => $team->slug]))
            ->assertOk();

        // The Save action is hidden in the view; the server guard refuses it too.
        Livewire::actingAs($viewer)
            ->test(Settings::class, ['team' => $team])
            ->call('save')
            ->assertForbidden();
    }

    public function test_an_update_holder_can_save(): void
    {
        Team::createRole('Editor', [TeamPermission::UPDATE_TEAM]);
        $owner = User::factory()->create();
        $team = Team::factory()->ownedBy($owner)->create(['name' => 'Old', 'slug' => 'old']);
        $editor = User::factory()->create();
        $team->addMember($editor, 'Editor');

        Livewire::actingAs($editor)
            ->test(Settings::class, ['team' => $team])
            ->set('data.name', 'New')
            ->set('data.slug', 'new-slug')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('New', $team->fresh()->name);
    }

    protected function tearDown(): void
    {
        app(TeamContext::class)->clear();

        parent::tearDown();
    }
}
