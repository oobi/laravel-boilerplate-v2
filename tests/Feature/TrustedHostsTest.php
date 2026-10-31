<?php

namespace Tests\Feature;

use App\Support\TrustedHosts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Outside `local` the app answers only to APP_URL, its subdomains and the
 * TrustedHosts list; any other Host, including one forwarded by a trusted
 * proxy, gets a 400 (GitHub oobi#59). Laravel skips the check in tests, so
 * these switch the environment to production.
 */
class TrustedHostsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.url' => 'https://myapp.test']);
        $this->app->detectEnvironment(fn (): string => 'production');
    }

    public function test_an_unknown_host_gets_a_400(): void
    {
        $this->get('https://evil.test/up')->assertBadRequest();
    }

    public function test_an_unknown_host_forwarded_by_a_trusted_proxy_gets_a_400(): void
    {
        config(['trustedproxy.proxies' => '*']);

        $this->get('https://myapp.test/up', ['X-Forwarded-Host' => 'evil.test'])->assertBadRequest();
    }

    public function test_the_app_url_host_and_its_subdomains_are_served(): void
    {
        $this->get('https://myapp.test/up')->assertOk();
        $this->get('https://www.myapp.test/up')->assertOk();
    }

    public function test_hosts_listed_in_trusted_hosts_are_served(): void
    {
        config(['app.trusted_hosts' => ['other.test', 'https://third.test:8443']]);

        $this->get('https://other.test/up')->assertOk();
        $this->get('https://third.test/up')->assertOk();
        $this->get('https://sub.other.test/up')->assertBadRequest();
    }

    public function test_any_host_is_served_locally(): void
    {
        $this->app->detectEnvironment(fn (): string => 'local');

        $this->get('https://evil.test/up')->assertOk();
    }

    public function test_patterns_match_hosts_exactly_or_with_subdomains(): void
    {
        $this->assertMatchesRegularExpression('{'.TrustedHosts::exact('A.test').'}', 'a.test');
        $this->assertDoesNotMatchRegularExpression('{'.TrustedHosts::exact('a.test').'}', 'xa.test');
        $this->assertMatchesRegularExpression('{'.TrustedHosts::withSubdomains('a.test').'}', 'x.y.a.test');
        $this->assertDoesNotMatchRegularExpression('{'.TrustedHosts::withSubdomains('a.test').'}', 'xa.test');
        $this->assertDoesNotMatchRegularExpression('{'.TrustedHosts::withSubdomains(':8080').'}', 'evil.test.');
        $this->assertDoesNotMatchRegularExpression('{'.TrustedHosts::exact('').'}', '');
    }
}
