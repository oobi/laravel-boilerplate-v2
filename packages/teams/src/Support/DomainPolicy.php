<?php

declare(strict_types=1);

namespace Concise\Teams\Support;

use Concise\Teams\Exceptions\DomainsMisconfigured;
use Illuminate\Support\Str;

/**
 * The custom-domain overlay's feature flag and its host configuration. Whether
 * it's active at all is config (infra-tied: wildcard DNS/TLS); *who* may manage a
 * team's domains is runtime authorization — the MANAGE_DOMAINS team permission
 * (held via a role) and system admins — not config. See ~dev/TEAMS_DOMAINS_SCOPE.md
 * and ~dev/TEAMS_DOMAINS_HOST_SPLIT.md.
 *
 * When enabled, the overlay switches routing from path mode (/{prefix}/{slug})
 * to host mode: three hosts, not one. `admin_host` is the admin area ONLY —
 * dashboard, users, roles, the system "all teams" area, impersonation — and
 * `admin_host`/`base` are therefore required whenever it's on
 * (assertConfigured() enforces that at boot, fail-loud). `account_host` is the
 * account layer everyone needs (auth, profile, team picker/onboarding,
 * invitations) — optional, defaulting to `base` (the apex). Teams live on
 * `{slug}.base` and their verified custom domains.
 */
final class DomainPolicy
{
    public static function enabled(): bool
    {
        return (bool) config('teams.domains.enabled', false);
    }

    /**
     * The explicit host the admin area — and ONLY the admin area — is served
     * from in host mode (never derived from APP_URL). Nothing outside
     * `ACCESS_ADMIN_PANEL` (or the impersonation leave route, deliberately
     * exempted from that gate) may ever bind here; that's what makes the host
     * safe to fence to a VPN/IP range later. See ~dev/TEAMS_DOMAINS_HOST_SPLIT.md.
     */
    public static function adminHost(): ?string
    {
        return self::host(config('teams.domains.admin_host'));
    }

    /**
     * The host the account layer (auth, profile, the team
     * picker/onboarding, invitation links) binds to in host mode: the
     * configured override, else `base` (the apex) — a Laravel-served public
     * landing can also serve `/login` and `/profile` with no extra DNS/TLS.
     * Null in path mode (no host constraint). Route groups pass this straight
     * to ->domain(); the admin area binds to adminHost() instead, never this.
     */
    public static function accountHost(): ?string
    {
        return self::enabled() ? (self::host(config('teams.domains.account_host')) ?? self::base()) : null;
    }

    /** The apex/base host the public landing binds to in host mode, or null in path mode. */
    public static function apexHost(): ?string
    {
        return self::enabled() ? self::base() : null;
    }

    /**
     * The route pattern for the `{teamHost}` domain parameter: a full dotted host,
     * excluding the admin host, the account host, and the apex. Without the
     * exclusion the wildcard team route would shadow those and 404 them for a
     * signed-in user (~dev/TEAMS_DOMAINS_SCOPE.md §5).
     */
    public static function teamHostPattern(): string
    {
        $excluded = array_unique(array_map(
            preg_quote(...),
            array_filter([self::adminHost(), self::accountHost(), self::base()]),
        ));

        return ($excluded === [] ? '' : '(?!(?:'.implode('|', $excluded).')$)').'[A-Za-z0-9.\-]+';
    }

    /** The registrable base each team's default subdomain hangs off ({slug}.base) in host mode. */
    public static function base(): ?string
    {
        return self::host(config('teams.domains.base'));
    }

    /**
     * Fail loud when the overlay is enabled without the hosts it needs. Called at
     * boot, so a misconfigured setup surfaces immediately rather than mis-routing.
     */
    public static function assertConfigured(): void
    {
        if (! self::enabled()) {
            return;
        }

        if (self::adminHost() === null || self::base() === null) {
            throw DomainsMisconfigured::missingHosts();
        }

        // The admin host is only fenceable if it's a host of its own — sharing it
        // with the apex or the account host would silently put auth/profile there.
        if (self::adminHost() === self::base() || self::adminHost() === self::accountHost()) {
            throw DomainsMisconfigured::adminHostNotIsolated();
        }
    }

    /**
     * Whether a leftmost host label is reserved (`www`, `admin`, `mail`, … from
     * `teams.domains.reserved`) — infra/system names a team must never occupy,
     * whether as a custom domain's label or as a `{slug}.base` subdomain.
     */
    public static function isReservedLabel(string $label): bool
    {
        $reserved = array_map(strtolower(...), (array) config('teams.domains.reserved', []));

        return in_array(Str::lower(trim($label)), $reserved, true);
    }

    /** Normalise a configured host to bare lowercase (no scheme/path), or null when blank. */
    private static function host(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $value = Str::lower(trim($value));
        $value = (string) preg_replace('#^https?://#', '', $value);

        return trim(explode('/', $value)[0], '.') ?: null;
    }
}
