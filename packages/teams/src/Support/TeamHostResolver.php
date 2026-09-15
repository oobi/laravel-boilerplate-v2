<?php

declare(strict_types=1);

namespace Concise\Teams\Support;

use Concise\Teams\Models\Domain;
use Concise\Teams\Models\Team;
use Illuminate\Support\Str;

/**
 * Maps a request host to the team it stands for, in host mode (the domains
 * overlay, 5h). Two hosts resolve to a team, in order:
 *
 *   1. a verified custom domain (the row's team) — only when the custom-domains
 *      tier is on; pending/unverified never match;
 *   2. the platform subdomain `{slug}.base` — no verification, the platform owns
 *      `*.base` via wildcard DNS.
 *
 * The control-plane host (`admin_host`) and anything else resolve to null, so the
 * caller (ResolveTeamContext) 404s rather than leaking a team. See
 * ~dev/TEAMS_DOMAINS_SCOPE.md §4.
 */
final class TeamHostResolver
{
    /**
     * A team's canonical host in host mode — its verified primary custom domain if
     * it has one, else the platform subdomain `{slug}.base`. The inverse of
     * resolve(); used by team_route() to emit host-rooted URLs.
     */
    public static function hostFor(Team $team): string
    {
        if (DomainPolicy::customDomainsEnabled()) {
            $primary = $team->primaryDomain();

            if ($primary !== null) {
                return $primary->domain;
            }
        }

        return $team->slug.'.'.DomainPolicy::base();
    }

    public static function resolve(string $host): ?Team
    {
        $host = Str::lower(trim($host));

        if ($host === '') {
            return null;
        }

        // The admin host and the account host are never a team, even if a slug happens to collide.
        if ($host === DomainPolicy::adminHost() || $host === DomainPolicy::accountHost()) {
            return null;
        }

        // A verified custom domain only routes when that tier is switched on;
        // otherwise a team is reachable only at its `{slug}.base` subdomain.
        if (DomainPolicy::customDomainsEnabled()) {
            $domain = Domain::query()
                ->where('domain', $host)
                ->whereNotNull('verified_at')
                ->first();

            if ($domain !== null) {
                return $domain->team;
            }
        }

        return self::resolveSubdomain($host);
    }

    /** A `{slug}.base` host → the team with that slug (single label, not the bare base). */
    private static function resolveSubdomain(string $host): ?Team
    {
        $base = DomainPolicy::base();

        if ($base === null || ! Str::endsWith($host, '.'.$base)) {
            return null;
        }

        $slug = Str::beforeLast($host, '.'.$base);

        // Exactly one label in front of the base: `acme.base`, not `a.b.base`.
        if ($slug === '' || Str::contains($slug, '.')) {
            return null;
        }

        // A reserved label (www, mail, …) is never a team, even if a row somehow
        // carries that slug — it belongs to infra, not a tenant.
        if (DomainPolicy::isReservedLabel($slug)) {
            return null;
        }

        return Team::query()->where('slug', $slug)->first();
    }
}
