<?php

namespace Tests\Feature\Teams;

use Concise\Teams\Models\Domain;
use Concise\Teams\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The custom-domain data model (5h.1). The routing/verification behaviour is
 * covered separately; this pins the model itself.
 */
class TeamDomainsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_domain_is_stored_canonical(): void
    {
        $domain = Domain::factory()->create(['domain' => 'HTTPS://Acme.COM/dashboard/']);

        $this->assertSame('acme.com', $domain->domain);
    }

    public function test_a_verification_token_and_record_are_generated_on_create(): void
    {
        $domain = Domain::factory()->create(['domain' => 'acme.com']);

        $this->assertNotEmpty($domain->verification_token);
        $this->assertSame('_bp-verify.acme.com', $domain->verificationHost());
        $this->assertStringContainsString($domain->verification_token, $domain->expectedTxtValue());
    }

    public function test_a_domain_starts_pending_and_can_be_marked_verified(): void
    {
        $domain = Domain::factory()->create();

        $this->assertFalse($domain->isVerified());

        $domain->markVerified();

        $this->assertTrue($domain->fresh()->isVerified());
    }

    public function test_primary_domain_returns_only_a_verified_primary(): void
    {
        $team = Team::factory()->create();

        // A pending primary does not count — only verified domains ever route.
        Domain::factory()->primary()->for($team)->create();
        $this->assertNull($team->primaryDomain());

        $verifiedPrimary = Domain::factory()->verified()->primary()->for($team)->create();
        $this->assertTrue($team->primaryDomain()->is($verifiedPrimary));
    }

    public function test_a_team_has_many_domains(): void
    {
        $team = Team::factory()->create();
        Domain::factory()->count(2)->for($team)->create();

        $this->assertCount(2, $team->domains);
    }
}
