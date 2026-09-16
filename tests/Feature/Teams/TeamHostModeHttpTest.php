<?php

namespace Tests\Feature\Teams;

use App\Models\User;
use Concise\Teams\Enums\TeamPermission;
use Concise\Teams\Models\Domain;
use Concise\Teams\Models\Team;
use Concise\Teams\Support\Navigation\TeamNavRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\URL;
use ReflectionProperty;
use Tests\TestCase;

/**
 * End-to-end host-mode routing over real HTTP dispatch (5h.6, deferred from
 * 5h.4b). TeamHostRoutingTest drives the resolver/middleware directly; this
 * boots the whole app in host mode and exercises the full request lifecycle —
 * real routes, middleware, and Fortify — against three hosts:
 *
 *   apex  myapp.test          → public landing + account layer (auth/profile)
 *   admin admin.myapp.test    → admin area ONLY
 *   *.myapp.test / custom     → a team's members-only pages
 *
 * The overlay decides at boot which route set registers, so host mode has to be
 * in the environment *before* the framework boots. The wrinkle: the env
 * repository is a process-wide static that immutably caches whatever `.env` was
 * first loaded — so once any path-mode test has booted, a later putenv can't
 * change what config() sees. Resetting that static (below) lets each of our
 * boots read the host-mode env we set, and lets the next test re-read its own.
 */
class TeamHostModeHttpTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN_HOST = 'admin.myapp.test';

    private const BASE = 'myapp.test';

    /** @var array<string, string|false> env captured before we forced host mode. */
    private array $originalEnv = [];

    protected function setUp(): void
    {
        $this->forceEnv([
            'TEAMS_DOMAINS_ENABLED' => 'true',
            'TEAMS_DOMAINS_CUSTOM' => 'true', // exercise the custom-domain path too
            'TEAMS_DOMAINS_ADMIN_HOST' => self::ADMIN_HOST,
            'TEAMS_DOMAINS_BASE' => self::BASE,
            'TEAMS_DOMAINS_ACCOUNT_HOST' => false, // → defaults to the apex (base)
        ]);
        $this->resetEnvRepository();

        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->restoreEnv();
        // Drop the cached host-mode env so the next test re-reads its own.
        $this->resetEnvRepository();
        // We booted with the custom-domains tier on, which registered the Domains
        // nav item into a process-wide static; clear it so a later path-mode test
        // doesn't inherit a stale link.
        TeamNavRegistry::flush();
    }

    // --- Team hosts ---------------------------------------------------------

    public function test_a_member_reaches_their_team_by_its_platform_subdomain(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['slug' => 'acme', 'name' => 'Acme Inc']);
        $team->addMember($user);

        $this->actingAs($user)
            ->get('http://acme.'.self::BASE.'/dashboard')
            ->assertOk()
            ->assertSee('Acme Inc');
    }

    public function test_the_team_host_root_redirects_to_the_dashboard(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['slug' => 'acme']);
        $team->addMember($user);

        $this->actingAs($user)
            ->get('http://acme.'.self::BASE.'/')
            ->assertRedirect('/dashboard');
    }

    public function test_a_member_reaches_their_team_by_a_verified_custom_domain(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['name' => 'Acme Inc']);
        $team->addMember($user);
        Domain::factory()->verified()->for($team)->create(['domain' => 'acme.com']);

        $this->actingAs($user)
            ->get('http://acme.com/dashboard')
            ->assertOk()
            ->assertSee('Acme Inc');
    }

    public function test_a_non_member_is_forbidden_on_a_team_host(): void
    {
        Team::factory()->create(['slug' => 'acme']);

        $this->actingAs(User::factory()->create())
            ->get('http://acme.'.self::BASE.'/dashboard')
            ->assertForbidden();
    }

    public function test_an_authorised_member_can_open_the_membership_gated_pages(): void
    {
        // Regression: in host mode the team is implied by the host, not a {team}
        // route parameter, so a page component's mount(Team $team) has no binding
        // to resolve — it must still receive the resolved team, or every
        // membership-gated page 403s even for a fully authorised member while the
        // ungated dashboard works. (Reported via impersonation: an impersonated
        // owner could see the dashboard but 403'd on members/settings.)
        Team::createRole('Manager', [TeamPermission::MANAGE_MEMBERS, TeamPermission::UPDATE_TEAM]);
        $user = User::factory()->create();
        $team = Team::factory()->create(['slug' => 'acme']);
        $team->addMember($user, 'Manager');

        $this->actingAs($user)->get('http://acme.'.self::BASE.'/members')->assertOk();
        $this->actingAs($user)->get('http://acme.'.self::BASE.'/settings')->assertOk();
    }

    public function test_impersonating_an_owner_lands_them_able_to_use_the_team(): void
    {
        // The reported scenario end to end: a system admin impersonates a team
        // owner who holds a roster-managing role, landing on the members page —
        // which must open, not 403.
        Team::createRole('Manager', [TeamPermission::MANAGE_MEMBERS]);
        $owner = User::factory()->create();
        $team = Team::factory()->create(['slug' => 'acme']);
        $team->addMember($owner, 'Manager');
        $admin = User::factory()->superAdmin()->create();

        $membersUrl = 'http://acme.'.self::BASE.'/members';

        $this->actingAs($admin)
            ->get(URL::signedRoute('users.impersonate', ['id' => $owner->id, 'next' => $membersUrl]))
            ->assertRedirect($membersUrl);

        // Now acting as the impersonated owner, the members page opens.
        $this->get($membersUrl)->assertOk();
    }

    public function test_an_unknown_team_host_is_not_found(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('http://ghost.'.self::BASE.'/dashboard')
            ->assertNotFound();
    }

    public function test_a_guest_on_a_team_host_is_sent_to_login_on_the_account_host(): void
    {
        $team = Team::factory()->create(['slug' => 'acme']);
        $team->addMember(User::factory()->create());

        // Auth lives on the account host (here the apex), never the team host.
        $this->get('http://acme.'.self::BASE.'/dashboard')
            ->assertRedirect('http://'.self::BASE.'/login');
    }

    // --- Host quarantine ----------------------------------------------------

    public function test_the_admin_dashboard_lives_on_the_admin_host(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->get('http://'.self::ADMIN_HOST.'/dashboard')
            ->assertOk();
    }

    public function test_the_admin_dashboard_is_not_served_from_the_apex(): void
    {
        // The admin area is quarantined to admin_host; the apex only carries the
        // public landing + account layer, so /dashboard there doesn't exist.
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->get('http://'.self::BASE.'/dashboard')
            ->assertNotFound();
    }

    public function test_the_public_landing_is_served_from_the_apex(): void
    {
        $this->get('http://'.self::BASE.'/')->assertOk();
    }

    public function test_the_settings_page_offers_domains_as_a_tab(): void
    {
        // Domains is now a tab beside Settings, not its own sidebar item: the
        // sidebar carries Settings (not Domains), and the Settings page surfaces
        // the Domains tab for a member holding MANAGE_DOMAINS. (MANAGE_DOMAINS
        // implies VIEW_SETTINGS, so the holder can open the Settings page.)
        Team::createRole('Domain Admin', [TeamPermission::MANAGE_DOMAINS]);
        $user = User::factory()->create();
        $team = Team::factory()->create(['slug' => 'acme']);
        $team->addMember($user, 'Domain Admin');

        // The sidebar links to Settings, not Domains, from the dashboard.
        $this->actingAs($user)
            ->get('http://acme.'.self::BASE.'/dashboard')
            ->assertOk()
            ->assertSee('http://acme.'.self::BASE.'/settings')
            ->assertDontSee('http://acme.'.self::BASE.'/domains');

        // The Domains tab appears once on the Settings page.
        $this->actingAs($user)
            ->get('http://acme.'.self::BASE.'/settings')
            ->assertOk()
            ->assertSee('http://acme.'.self::BASE.'/domains');
    }

    public function test_a_manage_domains_holder_opens_the_domains_page(): void
    {
        // The full Domains page (which renders the Settings/Domains tab bar) opens
        // for a member holding MANAGE_DOMAINS — covered here, in real host mode,
        // since its tabs generate host URLs (see DomainManagementTest's note).
        Team::createRole('Domain Admin', [TeamPermission::MANAGE_DOMAINS]);
        $user = User::factory()->create();
        $team = Team::factory()->create(['slug' => 'acme']);
        $team->addMember($user, 'Domain Admin');

        $this->actingAs($user)
            ->get('http://acme.'.self::BASE.'/domains')
            ->assertOk()
            ->assertSee(team_trans('domains.title'))
            // Both sibling tabs are present, linking back to Settings and to Domains.
            ->assertSee('http://acme.'.self::BASE.'/settings')
            ->assertSee('http://acme.'.self::BASE.'/domains');
    }

    /**
     * Force env values for the duration of the test, remembering the originals.
     *
     * @param  array<string, string|false>  $values  false unsets the key
     */
    private function forceEnv(array $values): void
    {
        foreach ($values as $key => $value) {
            $this->originalEnv[$key] = getenv($key);
            $this->putEnv($key, $value);
        }
    }

    private function restoreEnv(): void
    {
        foreach ($this->originalEnv as $key => $value) {
            $this->putEnv($key, $value);
        }

        $this->originalEnv = [];
    }

    private function putEnv(string $key, string|false $value): void
    {
        if ($value === false) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);

            return;
        }

        putenv("{$key}={$value}");
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }

    /**
     * Clear the framework's process-wide env repository so the next boot rebuilds
     * it from the current environment (the immutable cache otherwise pins the
     * first `.env` any test loaded).
     */
    private function resetEnvRepository(): void
    {
        $repository = new ReflectionProperty(Env::class, 'repository');
        $repository->setValue(null, null);
    }
}
