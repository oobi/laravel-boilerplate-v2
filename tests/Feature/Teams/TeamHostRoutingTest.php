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
 * Host-mode resolution and routing for the custom-domain overlay (5h.4): a
 * request host is mapped to its team (verified custom domain, or {slug}.{base}),
 * the control-plane host and anything else resolve to nothing, and the boot-time
 * config guard fails loud when the overlay is on without its hosts.
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

    public function test_the_control_plane_host_never_resolves_to_a_team(): void
    {
        $this->enableHostMode();
        // A team whose slug would otherwise produce the admin host: still not a team.
        Team::factory()->create(['slug' => 'admin']);

        $this->assertNull(TeamHostResolver::resolve('admin.myapp.com'));
        $this->assertNull(TeamHostResolver::resolve('unknown.example.org'));
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

    public function test_control_plane_and_apex_hosts_are_null_in_path_mode(): void
    {
        // In path mode the route groups bind to null → no host constraint (5h.4b).
        config(['teams.domains.enabled' => false, 'teams.domains.admin_host' => 'admin.myapp.com', 'teams.domains.base' => 'myapp.com']);

        $this->assertNull(DomainPolicy::controlPlaneHost());
        $this->assertNull(DomainPolicy::apexHost());
    }

    public function test_control_plane_and_apex_hosts_resolve_in_host_mode(): void
    {
        $this->enableHostMode();

        $this->assertSame('admin.myapp.com', DomainPolicy::controlPlaneHost());
        $this->assertSame('myapp.com', DomainPolicy::apexHost());
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
