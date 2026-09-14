<?php

namespace Tests\Feature\Teams;

use App\Models\User;
use Concise\Teams\Exceptions\DomainsMisconfigured;
use Concise\Teams\Http\Middleware\ResolveTeamContext;
use Concise\Teams\Models\Domain;
use Concise\Teams\Models\Team;
use Concise\Teams\Support\DomainPolicy;
use Concise\Teams\Support\TeamContext;
use Concise\Teams\Support\TeamHostResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

/**
 * Host-mode resolution and routing for the custom-domain overlay (5h.4,
 * reworked 5h.7 — see ~dev/TEAMS_DOMAINS_HOST_SPLIT.md): a request host is
 * mapped to its team (verified custom domain, or {slug}.{base}), the admin
 * host / account host / apex and anything else resolve to nothing, and the
 * boot-time config guard fails loud when the overlay is on without its
 * required hosts (admin_host, base — account_host is optional).
 */
class TeamHostRoutingTest extends TestCase
{
    use RefreshDatabase;

    private function enableHostMode(): void
    {
        config([
            'teams.domains.enabled' => true,
            'teams.domains.admin_host' => 'admin.myapp.com',
            'teams.domains.base' => 'myapp.com',
        ]);
    }

    // --- TeamHostResolver ---------------------------------------------------

    public function test_a_verified_custom_domain_resolves_to_its_team(): void
    {
        $this->enableHostMode();
        $team = Team::factory()->create();
        Domain::factory()->verified()->for($team)->create(['domain' => 'acme.com']);

        $this->assertTrue(TeamHostResolver::resolve('acme.com')->is($team));
    }

    public function test_a_pending_custom_domain_never_resolves(): void
    {
        $this->enableHostMode();
        $team = Team::factory()->create();
        Domain::factory()->for($team)->create(['domain' => 'acme.com']); // unverified

        $this->assertNull(TeamHostResolver::resolve('acme.com'));
    }

    public function test_a_platform_subdomain_resolves_by_slug(): void
    {
        $this->enableHostMode();
        $team = Team::factory()->create(['slug' => 'acme']);

        $this->assertTrue(TeamHostResolver::resolve('acme.myapp.com')->is($team));
    }

    public function test_the_bare_base_and_multi_label_subdomains_do_not_resolve(): void
    {
        $this->enableHostMode();
        Team::factory()->create(['slug' => 'acme']);

        $this->assertNull(TeamHostResolver::resolve('myapp.com'));       // the base itself
        $this->assertNull(TeamHostResolver::resolve('a.acme.myapp.com')); // two labels deep
    }

    public function test_the_admin_host_never_resolves_to_a_team(): void
    {
        $this->enableHostMode();
        // A team whose slug would otherwise produce the admin host: still not a team.
        Team::factory()->create(['slug' => 'admin']);

        $this->assertNull(TeamHostResolver::resolve('admin.myapp.com'));
        $this->assertNull(TeamHostResolver::resolve('unknown.example.org'));
    }

    public function test_the_account_host_never_resolves_to_a_team_even_when_overridden(): void
    {
        $this->enableHostMode();
        config(['teams.domains.account_host' => 'app.myapp.com']);
        // 'app' is on the default reserved list, so this couldn't happen via
        // Team::uniqueSlug — force it to prove the exclusion isn't relying on that alone.
        Team::factory()->create()->forceFill(['slug' => 'app'])->save();

        $this->assertNull(TeamHostResolver::resolve('app.myapp.com'));
    }

    public function test_a_reserved_subdomain_never_resolves_even_with_a_matching_slug(): void
    {
        $this->enableHostMode();
        // A row forced onto a reserved slug still doesn't route — infra names belong
        // to the platform, not a tenant.
        Team::factory()->create()->forceFill(['slug' => 'www'])->save();

        $this->assertNull(TeamHostResolver::resolve('www.myapp.com'));
    }

    public function test_unique_slug_avoids_reserved_labels_in_host_mode(): void
    {
        $this->enableHostMode();
        // 'Admin' slugifies to the reserved 'admin', so it must be bumped — otherwise
        // the team would be stranded (admin.myapp.com never resolves to it).
        $this->assertSame('admin-2', Team::uniqueSlug('Admin'));

        // Path mode has no subdomains, so the reserved list doesn't apply.
        config(['teams.domains.enabled' => false]);
        $this->assertSame('admin', Team::uniqueSlug('Admin'));
    }

    // --- TeamHostResolver::hostFor + team_route() ---------------------------

    public function test_host_for_prefers_a_verified_primary_domain_else_the_platform_subdomain(): void
    {
        $this->enableHostMode();
        $team = Team::factory()->create(['slug' => 'acme']);

        // No custom domain yet: the platform subdomain.
        $this->assertSame('acme.myapp.com', TeamHostResolver::hostFor($team));

        // A verified primary custom domain wins.
        Domain::factory()->verified()->primary()->for($team)->create(['domain' => 'acme.com']);
        $this->assertSame('acme.com', TeamHostResolver::hostFor($team->fresh()));
    }

    public function test_team_route_emits_a_path_url_in_path_mode(): void
    {
        config(['teams.domains.enabled' => false]);
        $team = Team::factory()->create(['slug' => 'acme']);

        $this->assertStringContainsString('/teams/acme/dashboard', team_route('team.dashboard', $team));
    }

    public function test_team_route_emits_a_host_rooted_url_in_host_mode(): void
    {
        $this->enableHostMode();
        $team = Team::factory()->create(['slug' => 'acme']);
        Domain::factory()->verified()->primary()->for($team)->create(['domain' => 'acme.com']);

        // A distinct name avoids colliding with the path-mode team.dashboard the app booted with.
        Route::domain('{teamHost}')->get('/probe-dash', fn () => '')->name('probe.teamdash');
        $this->app['router']->getRoutes()->refreshNameLookups();

        $url = team_route('probe.teamdash', $team->fresh());

        $this->assertSame('acme.com', parse_url($url, PHP_URL_HOST)); // the team's canonical host
        $this->assertSame('/probe-dash', parse_url($url, PHP_URL_PATH)); // no path slug segment
    }

    // --- DomainPolicy::assertConfigured -------------------------------------

    public function test_enabling_the_overlay_without_hosts_fails_loud(): void
    {
        config(['teams.domains.enabled' => true, 'teams.domains.admin_host' => null, 'teams.domains.base' => null]);

        $this->expectException(DomainsMisconfigured::class);
        DomainPolicy::assertConfigured();
    }

    public function test_a_configured_overlay_and_a_disabled_overlay_both_pass(): void
    {
        $this->enableHostMode();
        DomainPolicy::assertConfigured();

        config(['teams.domains.enabled' => false, 'teams.domains.admin_host' => null, 'teams.domains.base' => null]);
        DomainPolicy::assertConfigured();

        $this->expectNotToPerformAssertions();
    }

    public function test_account_and_apex_hosts_are_null_in_path_mode(): void
    {
        // In path mode the route groups bind to null → no host constraint (5h.4b).
        // adminHost() itself stays a raw accessor (also used for reservation
        // checks regardless of mode); routes/teams.php gates it at the call site.
        config(['teams.domains.enabled' => false, 'teams.domains.admin_host' => 'admin.myapp.com', 'teams.domains.base' => 'myapp.com']);

        $this->assertNull(DomainPolicy::accountHost());
        $this->assertNull(DomainPolicy::apexHost());
    }

    public function test_admin_and_apex_hosts_resolve_in_host_mode(): void
    {
        $this->enableHostMode();

        $this->assertSame('admin.myapp.com', DomainPolicy::adminHost());
        $this->assertSame('myapp.com', DomainPolicy::apexHost());
    }

    public function test_the_account_host_defaults_to_the_apex_and_honours_an_override(): void
    {
        $this->enableHostMode();

        // Unset: the account layer (login/profile/picker) lives on the apex, so a
        // Laravel-served public landing also serves /login and /profile.
        $this->assertSame('myapp.com', DomainPolicy::accountHost());

        // A headless-apex deployment overrides it to its own host.
        config(['teams.domains.account_host' => 'app.myapp.com']);
        $this->assertSame('app.myapp.com', DomainPolicy::accountHost());
    }

    public function test_the_team_host_pattern_excludes_the_admin_account_and_apex_hosts(): void
    {
        // Without this, the wildcard {teamHost} team route shadows those hosts and
        // 404s them for a signed-in user (regression guard).
        $this->enableHostMode();
        config(['teams.domains.account_host' => 'app.myapp.com']);
        $pattern = '/^'.DomainPolicy::teamHostPattern().'$/';

        $this->assertSame(0, preg_match($pattern, 'admin.myapp.com')); // admin host
        $this->assertSame(0, preg_match($pattern, 'app.myapp.com'));   // account host (overridden)
        $this->assertSame(0, preg_match($pattern, 'myapp.com'));       // apex
        $this->assertSame(1, preg_match($pattern, 'acme.myapp.com'));  // team subdomain
        $this->assertSame(1, preg_match($pattern, 'acme.com'));        // verified custom domain
    }

    // --- ResolveTeamContext (host branch) -----------------------------------
    //
    // Exercised at the middleware level (the {teamHost} route is bound directly);
    // the end-to-end host-routing HTTP path is covered in 5h.6.

    public function test_a_member_reaches_a_team_by_its_host_and_the_context_is_set(): void
    {
        $this->enableHostMode();
        $team = Team::factory()->create();
        $user = User::factory()->create();
        $team->addMember($user);
        Domain::factory()->verified()->for($team)->create(['domain' => 'acme.com']);

        $seen = null;
        $response = $this->runMiddleware('acme.com', $user, function () use (&$seen): Response {
            $seen = app(TeamContext::class)->get();

            return new Response('ok');
        });

        $this->assertSame('ok', $response->getContent());
        $this->assertNotNull($seen);
        $this->assertTrue($seen->is($team));
        // Context is cleared once the request is done.
        $this->assertFalse(app(TeamContext::class)->has());
    }

    public function test_a_non_member_is_forbidden_on_a_team_host(): void
    {
        $this->enableHostMode();
        $team = Team::factory()->create();
        Domain::factory()->verified()->for($team)->create(['domain' => 'acme.com']);

        try {
            $this->runMiddleware('acme.com', User::factory()->create());
            $this->fail('A non-member should be forbidden.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    public function test_an_unresolvable_host_is_not_found(): void
    {
        $this->enableHostMode();

        $this->expectException(NotFoundHttpException::class);
        $this->runMiddleware('nobody.example.org', User::factory()->create());
    }

    /** Run ResolveTeamContext for a request bound to the given team host. */
    private function runMiddleware(string $host, User $user, ?callable $next = null): Response
    {
        $request = Request::create('http://'.$host.'/dashboard');
        $request->setUserResolver(fn () => $user);

        $route = (new RoutingRoute('GET', 'dashboard', []))->bind($request);
        $route->setParameter('teamHost', $host);
        $request->setRouteResolver(fn () => $route);

        return (new ResolveTeamContext)->handle(
            $request,
            $next ?? fn (): Response => new Response('ok'),
        );
    }
}
