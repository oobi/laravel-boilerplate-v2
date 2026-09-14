<?php

declare(strict_types=1);

namespace Concise\Teams\Support;

use Concise\Teams\Exceptions\DomainsMisconfigured;
use Illuminate\Support\Str;

/**
 * The custom-domain overlay's feature flag and its host configuration. Whether
 * it's active at all is config (infra-tied: wildcard DNS/TLS); *who* may manage a
 * team's domains is runtime authorization — the MANAGE_DOMAINS team permission
 * (held via a role) and system admins — not config. See ~dev/TEAMS_DOMAINS_SCOPE.md.
 *
 * When enabled, the overlay switches routing from path mode (/{prefix}/{slug})
 * to host mode: the control plane on `admin_host`, teams on `{slug}.base` and
 * their verified custom domains. `admin_host` and `base` are therefore required
 * whenever it's on — assertConfigured() enforces that at boot (fail-loud).
 */
final class DomainPolicy
{
    public static function enabled(): bool
    {
        return (bool) config('teams.domains.enabled', false);
    }

    /** The explicit host the control plane is served from in host mode (never derived from APP_URL). */
    public static function adminHost(): ?string
    {
        return self::host(config('teams.domains.admin_host'));
    }

    /**
     * The host the control plane (admin, auth, team picker) binds to, or null in
     * path mode (no host constraint). Route groups pass this straight to
     * ->domain(); null leaves them unconstrained.
     */
    public static function controlPlaneHost(): ?string
    {
        return self::enabled() ? self::adminHost() : null;
    }

    /** The apex/base host the public landing binds to in host mode, or null in path mode. */
    public static function apexHost(): ?string
    {
        return self::enabled() ? self::base() : null;
    }

    /**
     * The route pattern for the `{teamHost}` domain parameter: a full dotted host,
     * excluding the control-plane host and the apex. Without the exclusion the
     * wildcard team route would shadow `admin_host/dashboard` (and the apex) and
     * 404 them for a signed-in user (~dev/TEAMS_DOMAINS_SCOPE.md §5).
     */
    public static function teamHostPattern(): string
    {
        $excluded = array_map(preg_quote(...), array_filter([self::adminHost(), self::base()]));

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
        if (self::enabled() && (self::adminHost() === null || self::base() === null)) {
            throw DomainsMisconfigured::missingHosts();
        }
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
