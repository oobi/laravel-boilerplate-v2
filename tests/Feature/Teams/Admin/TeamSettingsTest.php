<?php

namespace Tests\Feature\Teams\Admin;

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
            ->assertSet('data.active', true)
            ->set('data.name', 'Southwind')
            ->set('data.slug', 'southwind')
            ->set('data.active', false)
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('teams.settings', 'southwind'));

        $this->team->refresh();
        $this->assertSame('Southwind', $this->team->name);
        $this->assertSame('southwind', $this->team->slug);
        $this->assertFalse($this->team->active);
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
