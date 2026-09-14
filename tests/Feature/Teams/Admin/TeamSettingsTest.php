<?php

namespace Tests\Feature\Teams\Admin;

use App\Enums\SystemPermission;
use App\Models\User;
use Concise\Teams\Livewire\Admin\Teams\TeamSettings;
use Concise\Teams\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TeamSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->superAdmin()->create();
        $this->team = Team::factory()->create(['name' => 'Northwind', 'slug' => 'northwind']);
    }

    public function test_the_form_is_prefilled_and_saves(): void
    {
        Livewire::actingAs($this->admin)
            ->test(TeamSettings::class, ['team' => $this->team])
            ->assertSet('data.name', 'Northwind')
            ->assertSet('data.slug', 'northwind')
            ->set('data.name', 'Southwind')
            ->set('data.slug', 'southwind')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('teams.settings', 'southwind'));

        $this->team->refresh();
        $this->assertSame('Southwind', $this->team->name);
        $this->assertSame('southwind', $this->team->slug);
        $this->assertTrue($this->team->active, 'active is not a form field — only the deactivate action changes it');
    }

    public function test_the_slug_must_be_unique(): void
    {
        Team::factory()->create(['slug' => 'taken']);

        Livewire::actingAs($this->admin)
            ->test(TeamSettings::class, ['team' => $this->team])
            ->set('data.slug', 'taken')
            ->call('save')
            ->assertHasErrors(['data.slug']);
    }

    public function test_the_team_can_be_deactivated_and_reactivated(): void
    {
        $component = Livewire::actingAs($this->admin)
            ->test(TeamSettings::class, ['team' => $this->team])
            ->callAction('toggleActive');

        $this->assertFalse($this->team->fresh()->active);

        $component->callAction('toggleActive');

        $this->assertTrue($this->team->fresh()->active);
    }

    public function test_view_teams_opens_settings_read_only(): void
    {
        $viewer = User::factory()->withPermission(SystemPermission::VIEW_TEAMS)->create();

        $this->actingAs($viewer)->get(route('teams.settings', $this->team))
            ->assertOk()
            ->assertSee('Northwind')
            ->assertDontSee(__('admin.save_changes'));

        Livewire::actingAs($viewer)
            ->test(TeamSettings::class, ['team' => $this->team])
            ->assertFormFieldDisabled('name', 'form')
            ->assertActionHidden('toggleActive')
            ->assertActionHidden('deleteTeam')
            ->call('save')
            ->assertForbidden();

        $this->assertSame('Northwind', $this->team->fresh()->name);
    }

    public function test_manage_teams_edits_details_but_cannot_deactivate_or_delete(): void
    {
        $manager = User::factory()->withPermission(SystemPermission::MANAGE_TEAMS)->create();

        Livewire::actingAs($manager)
            ->test(TeamSettings::class, ['team' => $this->team])
            ->assertFormFieldEnabled('name', 'form')
            ->assertActionHidden('toggleActive')
            ->assertActionHidden('deleteTeam')
            ->set('data.name', 'Southwind')
            ->call('save')
            ->assertHasNoErrors();

        $this->team->refresh();
        $this->assertSame('Southwind', $this->team->name);
        $this->assertTrue($this->team->active);
    }

    public function test_deactivate_teams_alone_runs_the_toggle(): void
    {
        $operator = User::factory()->withPermission(SystemPermission::DEACTIVATE_TEAMS)->create();

        Livewire::actingAs($operator)
            ->test(TeamSettings::class, ['team' => $this->team])
            ->assertFormFieldDisabled('name', 'form')
            ->assertActionVisible('toggleActive')
            ->assertActionHidden('deleteTeam')
            ->callAction('toggleActive');

        $this->assertFalse($this->team->fresh()->active);
    }

    public function test_an_active_team_cannot_be_deleted(): void
    {
        // Disabled in the UI (with a "deactivate first" tooltip); Filament refuses to run a disabled action.
        Livewire::actingAs($this->admin)
            ->test(TeamSettings::class, ['team' => $this->team])
            ->assertActionDisabled('deleteTeam')
            ->callAction('deleteTeam');

        $this->assertNotSoftDeleted('teams', ['id' => $this->team->id]);
    }

    public function test_an_inactive_team_can_be_deleted(): void
    {
        $this->team->update(['active' => false]);

        Livewire::actingAs($this->admin)
            ->test(TeamSettings::class, ['team' => $this->team])
            ->assertActionEnabled('deleteTeam')
            ->callAction('deleteTeam')
            ->assertRedirect(route('teams.index'));

        $this->assertSoftDeleted('teams', ['id' => $this->team->id]);
    }
}
