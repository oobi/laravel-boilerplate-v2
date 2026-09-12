<?php

declare(strict_types=1);

namespace Concise\Teams\Actions;

use Concise\Teams\Models\Domain;
use Concise\Teams\Support\Dns\DnsResolver;

/**
 * The "verify now" write boundary (5h.2): a domain becomes verified only when
 * its expected TXT value is actually published at its verification host. Until
 * then it stays pending and never routes (OQ3). The DNS lookup is injected so
 * this is deterministic in tests (see FakeDnsResolver).
 */
class VerifyDomain
{
    public function __construct(private readonly DnsResolver $dns) {}

    /** Returns whether the domain is (now) verified. */
    public function __invoke(Domain $domain): bool
    {
        if ($domain->isVerified()) {
            return true;
        }

        $verified = in_array(
            $domain->expectedTxtValue(),
            $this->dns->txtRecords($domain->verificationHost()),
            strict: true,
        );

        if ($verified) {
            $domain->markVerified();
        }

        return $verified;
    }
}
