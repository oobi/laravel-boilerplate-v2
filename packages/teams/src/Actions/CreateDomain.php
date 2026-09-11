<?php

declare(strict_types=1);

namespace Concise\Teams\Actions;

use Concise\Teams\Models\Domain;
use Concise\Teams\Models\Team;
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
     * (`teams.domains.reserved`), or the whole domain is the app's own host or
     * the control-plane host — a team must never be able to claim those.
     */
    public static function isReserved(string $domain): bool
    {
        $label = explode('.', $domain)[0];
        $reserved = array_map(strtolower(...), (array) config('teams.domains.reserved', []));

        if (in_array($label, $reserved, true)) {
            return true;
        }

        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        return in_array($domain, array_filter([$appHost, config('teams.domains.admin_host')]), true);
    }
}
