<?php

namespace Tests\Feature\Teams;

use App\Models\User;
use Concise\Teams\Actions\CreateDomain;
use Concise\Teams\Enums\TeamPermission;
use Concise\Teams\Livewire\Team\Domains;
use Concise\Teams\Livewire\Team\ManageDomains;
use Concise\Teams\Models\Domain;
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

    /**
     * admin_host and account_host must never be claimable as a custom domain
     * either — checked as whole-host equality (not just the leftmost-label
     * blacklist), so a non-reserved label still can't be used to squat on
     * either host (~dev/TEAMS_DOMAINS_HOST_SPLIT.md D-DOM-5).
     */
    public function test_it_rejects_the_admin_host(): void
    {
        config([
            'teams.domains.enabled' => true,
            'teams.domains.admin_host' => 'portal.myapp.test',
            'teams.domains.base' => 'myapp.test',
        ]);

        $this->expectException(InvalidArgumentException::class);
        app(CreateDomain::class)(Team::factory()->create(), 'portal.myapp.test');
    }

    public function test_it_rejects_the_account_host(): void
    {
        config([
            'teams.domains.enabled' => true,
            'teams.domains.admin_host' => 'admin.myapp.test',
            'teams.domains.account_host' => 'members.myapp.test',
            'teams.domains.base' => 'myapp.test',
        ]);

        $this->expectException(InvalidArgumentException::class);
        app(CreateDomain::class)(Team::factory()->create(), 'members.myapp.test');
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

    public function test_the_domains_component_is_forbidden_when_only_subdomains_are_on(): void
    {
        // Host mode (subdomains) on, but the custom-domains tier off: the domains
        // surface stays closed even for an owner who holds MANAGE_DOMAINS.
        config(['teams.domains.enabled' => true, 'teams.domains.custom_domains' => false]);
        $owner = User::factory()->create();
        $team = Team::factory()->ownedBy($owner)->create();

        Livewire::actingAs($owner)
            ->test(ManageDomains::class, ['team' => $team])
            ->assertForbidden();
    }

    public function test_an_owner_with_the_permission_manages_domains(): void
    {
        // The default owner role ('Team Admin', seeded in setUp) carries MANAGE_DOMAINS,
        // so the owner it's given at setup can manage — authority via role, not ownership.
        $this->enableCustomDomains();
        $owner = User::factory()->create();
        $team = Team::factory()->ownedBy($owner)->create();

        Livewire::actingAs($owner)
            ->test(ManageDomains::class, ['team' => $team])
            ->assertActionVisible('addDomain');
    }

    public function test_an_owner_without_the_permission_cannot_manage_domains(): void
    {
        // Ownership grants no bypass: an owner whose role lacks MANAGE_DOMAINS can't view or act.
        $this->enableCustomDomains();
        Team::createRole('Staff', [TeamPermission::UPDATE_TEAM]); // no manage-domains
        $owner = User::factory()->create();
        $team = Team::factory()->ownedBy($owner)->create();
        $team->syncMemberRoles($owner, ['Staff']); // replace the default admin role

        Livewire::actingAs($owner)
            ->test(ManageDomains::class, ['team' => $team])
            ->assertForbidden();
    }

    public function test_a_system_admin_always_manages(): void
    {
        $this->enableCustomDomains();
        $team = Team::factory()->create();

        Livewire::actingAs(User::factory()->superAdmin()->create())
            ->test(ManageDomains::class, ['team' => $team])
            ->assertActionVisible('addDomain');
    }

    public function test_domains_can_be_searched_and_filtered_by_status(): void
    {
        $this->enableCustomDomains();
        $team = Team::factory()->create();
        $verified = Domain::factory()->verified()->for($team)->create(['domain' => 'verified.example.com']);
        $pending = Domain::factory()->for($team)->create(['domain' => 'pending.example.org']); // unverified

        Livewire::actingAs(User::factory()->superAdmin()->create())
            ->test(ManageDomains::class, ['team' => $team])
            ->assertCanSeeTableRecords([$verified, $pending])
            ->searchTable('verified.example')
            ->assertCanSeeTableRecords([$verified])
            ->assertCanNotSeeTableRecords([$pending])
            ->searchTable('')
            ->filterTable('status', 'pending')
            ->assertCanSeeTableRecords([$pending])
            ->assertCanNotSeeTableRecords([$verified])
            ->filterTable('status', 'verified')
            ->assertCanSeeTableRecords([$verified])
            ->assertCanNotSeeTableRecords([$pending]);
    }

    // Team-area Settings page access (view vs edit) lives in TeamSettingsTest.

    // --- the team-area Domains page (its own tab) ---
    //
    // Driven as the component (not a full-page GET): enabling custom domains
    // turns host mode on, and host URLs can't be generated against path-mode
    // routes in one booted app (see the 5h.5 runtime note). The full host-mode
    // path is covered by TeamHostModeHttpTest.

    public function test_the_domains_page_404s_when_the_tier_is_off(): void
    {
        // The route is always registered, but the page is inert unless custom domains are on.
        $owner = User::factory()->create();
        $team = Team::factory()->ownedBy($owner)->create();

        Livewire::actingAs($owner)
            ->test(Domains::class, ['team' => $team])
            ->assertNotFound();
    }

    public function test_a_manage_domains_holder_opens_the_domains_page(): void
    {
        $this->enableCustomDomains();
        $owner = User::factory()->create();
        $team = Team::factory()->ownedBy($owner)->create();

        Livewire::actingAs($owner)
            ->test(Domains::class, ['team' => $team])
            ->assertOk()
            ->assertSee(team_trans('nav.domains'));
    }

    public function test_a_member_without_the_permission_cannot_open_the_domains_page(): void
    {
        $this->enableCustomDomains();
        Team::createRole('Staff', [TeamPermission::UPDATE_TEAM]); // no manage-domains
        $owner = User::factory()->create();
        $team = Team::factory()->ownedBy($owner)->create();
        $team->syncMemberRoles($owner, ['Staff']);

        Livewire::actingAs($owner)
            ->test(Domains::class, ['team' => $team])
            ->assertForbidden();
    }

    public function test_the_sidebar_offers_no_domains_link_when_the_tier_is_off(): void
    {
        // The nav item is registered at boot only when custom domains are on (off here).
        $owner = User::factory()->create();
        $team = Team::factory()->ownedBy($owner)->create();

        $this->actingAs($owner)
            ->get(route('team.dashboard', ['team' => $team->slug]))
            ->assertOk()
            ->assertDontSee(route('team.domains', ['team' => $team->slug]));
    }

    /** Host mode plus the custom-domains tier — what makes the domains surface live. */
    private function enableCustomDomains(): void
    {
        config(['teams.domains.enabled' => true, 'teams.domains.custom_domains' => true]);
    }

    protected function tearDown(): void
    {
        app(TeamContext::class)->clear();

        parent::tearDown();
    }
}
