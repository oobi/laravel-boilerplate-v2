<?php

declare(strict_types=1);

namespace Concise\Teams\Support;

use Closure;
use Concise\Teams\Models\Team;
use Illuminate\Support\Facades\Cache;

/**
 * A thin cache wrapper that namespaces every key to one team
 * (`teams:{team_id}:…`), so no team can read or clobber another team's cached
 * data (~dev/TEAMS_TIER_SCOPE.md §5.5). Key-prefixing (not cache tags) is used so
 * it works on every cache driver, including file and database.
 */
class TeamCache
{
    public function __construct(protected Team $team) {}

    public static function for(Team $team): self
    {
        return new self($team);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return Cache::get($this->key($key), $default);
    }

    public function put(string $key, mixed $value, mixed $ttl = null): bool
    {
        return Cache::put($this->key($key), $value, $ttl);
    }

    public function has(string $key): bool
    {
        return Cache::has($this->key($key));
    }

    public function forget(string $key): bool
    {
        return Cache::forget($this->key($key));
    }

    public function remember(string $key, mixed $ttl, Closure $callback): mixed
    {
        return Cache::remember($this->key($key), $ttl, $callback);
    }

    /** The fully-qualified, team-namespaced cache key. */
    public function key(string $key): string
    {
        return config('teams.cache.prefix', 'teams').':'.$this->team->getKey().':'.$key;
    }
}
