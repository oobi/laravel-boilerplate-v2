<?php

declare(strict_types=1);

namespace Concise\Teams\Support\Dns;

/**
 * The production DnsResolver — a real TXT lookup via PHP's resolver. Failures
 * (NXDOMAIN, timeouts) surface as no records rather than an error, so an
 * unprovable domain simply stays pending.
 */
class SystemDnsResolver implements DnsResolver
{
    public function txtRecords(string $host): array
    {
        $records = @dns_get_record($host, DNS_TXT) ?: [];

        return collect($records)
            ->map(fn (array $record): string => isset($record['entries']) && is_array($record['entries'])
                ? implode('', $record['entries'])   // long TXT split into chunks
                : (string) ($record['txt'] ?? ''))
            ->filter()
            ->values()
            ->all();
    }
}
