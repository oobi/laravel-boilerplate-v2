<?php

namespace Tests\Feature\Teams;

use Concise\Teams\Actions\VerifyDomain;
use Concise\Teams\Models\Domain;
use Concise\Teams\Support\Dns\DnsResolver;
use Concise\Teams\Support\Dns\FakeDnsResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Domain verification (5h.2). DNS is faked via FakeDnsResolver bound into the
 * container, so a domain is verified only when the expected TXT value is
 * "published" at its verification host — no real network.
 */
class DomainVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function fakeDns(array $records = []): void
    {
        $this->app->instance(DnsResolver::class, new FakeDnsResolver($records));
    }

    public function test_a_domain_verifies_when_the_txt_record_matches(): void
    {
        $domain = Domain::factory()->create(['domain' => 'acme.com']);
        $this->fakeDns([$domain->verificationHost() => [$domain->expectedTxtValue()]]);

        $this->assertTrue(app(VerifyDomain::class)($domain));
        $this->assertTrue($domain->fresh()->isVerified());
    }

    public function test_a_domain_stays_pending_without_the_record(): void
    {
        $domain = Domain::factory()->create();
        $this->fakeDns(); // nothing published

        $this->assertFalse(app(VerifyDomain::class)($domain));
        $this->assertFalse($domain->fresh()->isVerified());
    }

    public function test_a_wrong_token_does_not_verify(): void
    {
        $domain = Domain::factory()->create();
        $this->fakeDns([$domain->verificationHost() => ['bp-verify=someone-elses-token']]);

        $this->assertFalse(app(VerifyDomain::class)($domain));
        $this->assertFalse($domain->fresh()->isVerified());
    }

    public function test_an_already_verified_domain_short_circuits(): void
    {
        $domain = Domain::factory()->verified()->create();
        $this->fakeDns(); // even with no record, it stays verified

        $this->assertTrue(app(VerifyDomain::class)($domain));
    }
}
