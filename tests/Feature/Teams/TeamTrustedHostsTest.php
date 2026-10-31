<?php

namespace Tests\Feature\Teams;

use Concise\Teams\Models\Domain;
use Concise\Teams\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * In host mode the app also answers on `base` and its subdomains, the admin and
 * account hosts, and verified custom domains (GitHub oobi#59). Laravel skips
 * the trusted-host check in tests, so these switch the environment to
 * production and probe the health route, which every host serves.
 */
class TeamTrustedHostsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.url' => 'https://myapp.test',
            'teams.domains.enabled' => true,
            'teams.domains.base' => 'teams.example.test',
            'teams.domains.admin_host' => 'control.ops.test',
            'teams.domains.account_host' => 'account.elsewhere.test',
        ]);
        $this->app->detectEnvironment(fn (): string => 'production');
    }

    public function test_the_host_mode_hosts_are_served(): void
    {
        foreach (['teams.example.test', 'acme.teams.example.test', 'control.ops.test', 'account.elsewhere.test'] as $host) {
            $this->get("https://{$host}/up")->assertOk();
        }

        $this->get('https://x.control.ops.test/up')->assertBadRequest();
        $this->get('https://evil.test/up')->assertBadRequest();
    }

    public function test_in_path_mode_only_the_app_url_is_served(): void
    {
        config(['teams.domains.enabled' => false]);

        $this->get('https://control.ops.test/up')->assertBadRequest();
    }

    public function test_verified_custom_domains_are_served_and_pending_ones_are_not(): void
    {
        config(['teams.domains.custom_domains' => true]);
        $team = Team::factory()->create();
        Domain::factory()->verified()->for($team)->create(['domain' => 'portal.acmecorp.test']);
        Domain::factory()->for($team)->create(['domain' => 'shop.acmecorp.test']);

        $this->get('https://portal.acmecorp.test/up')->assertOk();
        $this->get('https://shop.acmecorp.test/up')->assertBadRequest();
    }

    public function test_custom_domains_are_not_served_while_that_tier_is_off(): void
    {
        Domain::factory()->verified()->for(Team::factory()->create())->create(['domain' => 'portal.acmecorp.test']);

        $this->get('https://portal.acmecorp.test/up')->assertBadRequest();
    }

    public function test_a_domain_is_served_as_soon_as_it_is_verified_and_refused_once_removed(): void
    {
        config(['teams.domains.custom_domains' => true]);
        $domain = Domain::factory()->for(Team::factory()->create())->create(['domain' => 'shop.acmecorp.test']);
        $this->get('https://shop.acmecorp.test/up')->assertBadRequest();

        $domain->markVerified();
        $this->get('https://shop.acmecorp.test/up')->assertOk();

        $domain->delete();
        $this->get('https://shop.acmecorp.test/up')->assertBadRequest();
    }

    public function test_a_permanently_deleted_teams_domains_are_refused_at_once(): void
    {
        config(['teams.domains.custom_domains' => true]);
        $team = Team::factory()->create();
        Domain::factory()->verified()->for($team)->create(['domain' => 'portal.acmecorp.test']);
        $this->get('https://portal.acmecorp.test/up')->assertOk();

        $team->forceDelete();

        $this->get('https://portal.acmecorp.test/up')->assertBadRequest();
    }
}
