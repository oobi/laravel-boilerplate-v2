<?php

namespace Tests\Feature\Teams;

use App\Models\User;
use Concise\Teams\Actions\CreateDomain;
use Concise\Teams\Livewire\Team\ManageDomains;
use Concise\Teams\Models\Team;
use Concise\Teams\Support\TeamContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The add-domain write boundary (validation) and the team-area access modes
 * (`teams.domains.team_access`) for the domains management surface (5h.3).
 */
class DomainManagementTest extends TestCase
{
    use RefreshDatabase;

    // --- CreateDomain validation ---

    public function test_it_creates_a_valid_pending_domain(): void
    {
        $domain = app(CreateDomain::class)(Team::factory()->create(), 'Shop.Example.COM/');

        $this->assertSame('shop.example.com', $domain->domain);
        $this->assertFalse($domain->isVerified());
    }

    public function test_it_rejects_a_malformed_domain(): void
    {
        $this->expectException(InvalidArgumentException::class);
        app(CreateDomain::class)(Team::factory()->create(), 'not a domain');
    }

    public function test_it_rejects_a_reserved_leftmost_label(): void
    {
        $this->expectException(InvalidArgumentException::class);
        app(CreateDomain::class)(Team::factory()->create(), 'admin.acme.com');
    }

    public function test_it_rejects_the_application_host(): void
    {
        config(['app.url' => 'https://myapp.test']);

        $this->expectException(InvalidArgumentException::class);
        app(CreateDomain::class)(Team::factory()->create(), 'myapp.test');
    }

    public function test_it_rejects_a_duplicate_regardless_of_casing(): void
    {
        $team = Team::factory()->create();
        app(CreateDomain::class)($team, 'acme.com');

        $this->expectException(InvalidArgumentException::class);
        app(CreateDomain::class)($team, 'ACME.com');
    }

    // --- team-area access modes ---

    public function test_the_team_settings_page_404s_when_the_overlay_is_off(): void
    {
        // enabled defaults to false
        $owner = User::factory()->create();
        $team = Team::factory()->ownedBy($owner)->create();

        $this->actingAs($owner)
            ->get(route('team.settings', ['team' => $team->slug]))
            ->assertNotFound();
    }

    public function test_a_team_owner_manages_domains_when_access_is_manage(): void
    {
        config(['teams.domains.enabled' => true, 'teams.domains.team_access' => 'manage']);
        $owner = User::factory()->create();
        $team = Team::factory()->ownedBy($owner)->create();

        Livewire::actingAs($owner)
            ->test(ManageDomains::class, ['team' => $team])
            ->assertActionVisible('addDomain');
    }

    public function test_read_only_lets_a_team_owner_view_but_not_manage(): void
    {
        config(['teams.domains.enabled' => true, 'teams.domains.team_access' => 'read-only']);
        $owner = User::factory()->create();
        $team = Team::factory()->ownedBy($owner)->create();

        // The component mounts (view allowed) but the add action is hidden.
        Livewire::actingAs($owner)
            ->test(ManageDomains::class, ['team' => $team])
            ->assertActionHidden('addDomain');
    }

    public function test_access_none_404s_the_team_settings_page(): void
    {
        config(['teams.domains.enabled' => true, 'teams.domains.team_access' => 'none']);
        $owner = User::factory()->create();
        $team = Team::factory()->ownedBy($owner)->create();

        $this->actingAs($owner)
            ->get(route('team.settings', ['team' => $team->slug]))
            ->assertNotFound();
    }

    public function test_a_system_admin_always_manages_even_when_team_access_is_none(): void
    {
        config(['teams.domains.enabled' => true, 'teams.domains.team_access' => 'none']);
        $admin = User::factory()->superAdmin()->create();
        $team = Team::factory()->create();

        Livewire::actingAs($admin)
            ->test(ManageDomains::class, ['team' => $team])
            ->assertActionVisible('addDomain');
    }

    protected function tearDown(): void
    {
        app(TeamContext::class)->clear();

        parent::tearDown();
    }
}
