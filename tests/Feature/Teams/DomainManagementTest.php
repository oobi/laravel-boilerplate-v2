<?php

namespace Tests\Feature\Teams;

use App\Models\User;
use Concise\Teams\Actions\CreateDomain;
use Concise\Teams\Enums\TeamPermission;
use Concise\Teams\Livewire\Team\ManageDomains;
use Concise\Teams\Models\Team;
use Concise\Teams\Support\TeamContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The add-domain write boundary (validation) and who may view/manage a team's
 * domains — runtime authorization via the MANAGE_DOMAINS permission + ownership
 * model, not config (5h.3, reworked).
 */
class DomainManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed the team permission rows so the policy's checkPermissionTo resolves
        // (createRole first-or-creates them); the role is left unassigned.
        Team::createRole('Team Admin', [
            TeamPermission::MANAGE_DOMAINS,
            TeamPermission::UPDATE_TEAM,
            TeamPermission::MANAGE_MEMBERS,
        ]);
    }

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

    // --- who may view / manage domains ---

    public function test_the_domains_component_is_forbidden_when_the_overlay_is_off(): void
    {
        // enabled defaults to false → even an owner can't view.
        $owner = User::factory()->create();
        $team = Team::factory()->ownedBy($owner)->create();

        Livewire::actingAs($owner)
            ->test(ManageDomains::class, ['team' => $team])
            ->assertForbidden();
    }

    public function test_a_sovereign_owner_manages_domains(): void
    {
        config(['teams.domains.enabled' => true, 'teams.ownership' => 'sovereign']);
        $owner = User::factory()->create();
        $team = Team::factory()->ownedBy($owner)->create();

        Livewire::actingAs($owner)
            ->test(ManageDomains::class, ['team' => $team])
            ->assertActionVisible('addDomain');
    }

    public function test_a_managed_owner_without_the_permission_is_read_only(): void
    {
        config(['teams.domains.enabled' => true, 'teams.ownership' => 'managed']);
        $owner = User::factory()->create();
        $team = Team::factory()->ownedBy($owner)->create();

        // Owner still sees it (read-only floor) but can't act — no bypass, no role.
        Livewire::actingAs($owner)
            ->test(ManageDomains::class, ['team' => $team])
            ->assertActionHidden('addDomain');
    }

    public function test_a_managed_owner_with_the_permission_manages(): void
    {
        config(['teams.domains.enabled' => true, 'teams.ownership' => 'managed']);
        Team::createRole('Manager', [TeamPermission::MANAGE_DOMAINS]);
        $owner = User::factory()->create();
        $team = Team::factory()->ownedBy($owner)->create();
        $team->syncMemberRoles($owner, ['Manager']);

        Livewire::actingAs($owner)
            ->test(ManageDomains::class, ['team' => $team])
            ->assertActionVisible('addDomain');
    }

    public function test_a_system_admin_always_manages(): void
    {
        config(['teams.domains.enabled' => true, 'teams.ownership' => 'managed']);
        $team = Team::factory()->create();

        Livewire::actingAs(User::factory()->superAdmin()->create())
            ->test(ManageDomains::class, ['team' => $team])
            ->assertActionVisible('addDomain');
    }

    // Team-area Settings page access (view vs edit) lives in TeamSettingsTest.

    protected function tearDown(): void
    {
        app(TeamContext::class)->clear();

        parent::tearDown();
    }
}
