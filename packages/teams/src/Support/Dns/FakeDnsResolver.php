<?php

declare(strict_types=1);

namespace Concise\Teams\Support\Dns;

/**
 * An in-memory DnsResolver for tests: seed it with host => TXT values and bind
 * it in place of SystemDnsResolver. Shipped with the tier so a consuming app's
 * tests can drive domain verification the same way. See ~dev/TEAMS_DOMAINS_SCOPE.md.
 */
class FakeDnsResolver implements DnsResolver
{
    /**
     * @param  array<string, list<string>>  $records  host => TXT values
     */
    public function __construct(private array $records = []) {}

    public function txtRecords(string $host): array
    {
        return $this->records[$host] ?? [];
    }

    /**
     * @param  list<string>  $values
     */
    public function set(string $host, array $values): void
    {
        $this->records[$host] = $values;
    }
}
