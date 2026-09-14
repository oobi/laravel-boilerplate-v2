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
 *   1. a verified custom domain (the row's team) — pending/unverified never match;
 *   2. the platform subdomain `{slug}.base` — no verification, the platform owns
 *      `*.base` via wildcard DNS.
 *
 * The control-plane host (`admin_host`) and anything else resolve to null, so the
 * caller (ResolveTeamContext) 404s rather than leaking a team. See
 * ~dev/TEAMS_DOMAINS_SCOPE.md §4.
 */
final class TeamHostResolver
{
    public static function resolve(string $host): ?Team
    {
        $host = Str::lower(trim($host));

        if ($host === '') {
            return null;
        }

        // The control plane is never a team, even if a slug happens to collide.
        if ($host === DomainPolicy::adminHost()) {
            return null;
        }

        $domain = Domain::query()
            ->where('domain', $host)
            ->whereNotNull('verified_at')
            ->first();

        if ($domain !== null) {
            return $domain->team;
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

        return Team::query()->where('slug', $slug)->first();
    }
}
