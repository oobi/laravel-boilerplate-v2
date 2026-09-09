<?php

declare(strict_types=1);

namespace Concise\Teams\Facades;

use Concise\Teams\Models\Team;
use Concise\Teams\Support\TeamCache;
use Concise\Teams\Support\TeamContext;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Facade;

/**
 * @method static void set(Team $team)
 * @method static void clear()
 * @method static Team|null get()
 * @method static bool has()
 * @method static Team getOrFail()
 * @method static int|string|null id()
 * @method static mixed run(Team $team, callable $callback)
 * @method static Filesystem disk()
 * @method static TeamCache cache()
 *
 * @see TeamContext
 */
class CurrentTeam extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return TeamContext::class;
    }
}
