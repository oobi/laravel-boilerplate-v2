<?php

declare(strict_types=1);

namespace Concise\Teams\Actions;

use Concise\Teams\Models\Domain;
use Concise\Teams\Models\Team;
use Concise\Teams\Support\DomainPolicy;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * The add-domain write boundary (5h.3): validates and records a pending custom
 * domain for a team. The Filament form mirrors these rules for friendly
 * messages; this enforces them regardless of caller. A new domain is always
 * pending — it must pass DNS verification before it can route (see VerifyDomain).
 */
class CreateDomain
{
    public function __invoke(Team $team, string $domain): Domain
    {
        $domain = (string) Domain::normalize($domain);

        if (! self::isValidHostname($domain)) {
            throw new InvalidArgumentException(team_trans('domains.invalid'));
        }

        if (self::isReserved($domain)) {
            throw new InvalidArgumentException(team_trans('domains.reserved'));
        }

        if (Domain::query()->where('domain', $domain)->exists()) {
            throw new InvalidArgumentException(team_trans('domains.taken'));
        }

        return $team->domains()->create(['domain' => $domain]);
    }

    /** A dotted hostname of valid labels, ≤253 chars, at least two labels. */
    public static function isValidHostname(string $domain): bool
    {
        return (bool) preg_match('/^(?=.{1,253}$)[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/', $domain);
    }

    /**
     * Reserved when the leftmost label is on the blacklist
     * (`teams.domains.reserved`), or the domain is, sits under, or is a parent
     * of a host the platform owns: `base` (so another team's `{slug}.base`, GitHub
     * oobi#26), the app's own host, the admin host or the account host. A team
     * must never be able to claim those or the operator's own zone.
     */
    public static function isReserved(string $domain): bool
    {
        if (DomainPolicy::isReservedLabel(explode('.', $domain)[0])) {
            return true;
        }

        $platformHosts = array_filter([
            DomainPolicy::base(),
            Str::lower(trim((string) parse_url((string) config('app.url'), PHP_URL_HOST), '.')),
            DomainPolicy::adminHost(),
            DomainPolicy::accountHost(),
        ]);

        foreach ($platformHosts as $host) {
            if ($domain === $host || Str::endsWith($domain, '.'.$host) || Str::endsWith($host, '.'.$domain)) {
                return true;
            }
        }

        return false;
    }
}
