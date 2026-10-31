<?php

declare(strict_types=1);

namespace App\Support;

use Closure;

/**
 * The hosts the app answers to, beyond APP_URL and its subdomains (which the
 * framework's TrustHosts middleware adds itself; see bootstrap/app.php). A
 * request for any other host gets a 400 the first time the app reads the host
 * (building a URL, matching a host route), so nothing built from the request
 * host can be attacker-chosen, including via X-Forwarded-Host from a trusted
 * proxy (GitHub oobi#59). Laravel skips the check in `local` and in tests.
 *
 * Holds the `app.trusted_hosts` extras (TRUSTED_HOSTS) plus whatever add-ons
 * register from their provider (mirrors LoginRedirectRegistry); the teams tier
 * registers its host-mode hosts and verified custom domains.
 */
class TrustedHosts
{
    /** A pattern that matches no host, for an empty one. */
    private const NEVER = '^(?!)$';

    /** @var array<int, Closure(): list<string>> */
    private static array $sources = [];

    /**
     * @param  Closure(): list<string>  $source  Returns host patterns (see exact() and withSubdomains()), read per request.
     */
    public static function register(Closure $source): void
    {
        self::$sources[] = $source;
    }

    /**
     * Every trusted host pattern, for the TrustHosts middleware.
     *
     * @return list<string>
     */
    public static function patterns(): array
    {
        return collect((array) config('app.trusted_hosts', []))
            ->map(self::exact(...))
            ->merge(collect(self::$sources)->flatMap(fn (Closure $source): array => $source()))
            ->unique()
            ->values()
            ->all();
    }

    /** A pattern matching exactly this host (a scheme, port or path is ignored). */
    public static function exact(string $host): string
    {
        $host = self::bare($host);

        return $host === '' ? self::NEVER : '^'.preg_quote($host).'$';
    }

    /** A pattern matching this host and any subdomain of it, at any depth. */
    public static function withSubdomains(string $host): string
    {
        $host = self::bare($host);

        // Empty, the subdomain part alone would match any host ending in a dot.
        return $host === '' ? self::NEVER : '^(.+\.)?'.preg_quote($host).'$';
    }

    /** `https://Example.com:8443/x` -> `example.com`. */
    private static function bare(string $host): string
    {
        $host = (string) preg_replace('#^[a-z][a-z0-9+.-]*://#i', '', strtolower(trim($host)));

        return trim((string) preg_replace('#[:/].*$#', '', $host), '.');
    }

    /** Drop all registered sources, for tests. */
    public static function flush(): void
    {
        self::$sources = [];
    }
}
