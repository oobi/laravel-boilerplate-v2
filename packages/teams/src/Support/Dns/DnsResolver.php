<?php

declare(strict_types=1);

namespace Concise\Teams\Support\Dns;

/**
 * The DNS boundary domain verification depends on. Injected so verification is
 * testable without real network lookups — bind FakeDnsResolver in tests. See
 * ~dev/TEAMS_DOMAINS_SCOPE.md (5h.2).
 */
interface DnsResolver
{
    /**
     * The TXT record values published at $host (empty if none / lookup fails).
     *
     * @return list<string>
     */
    public function txtRecords(string $host): array;
}
