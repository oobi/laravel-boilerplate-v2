<?php

declare(strict_types=1);

namespace App\Support;

use Closure;
use Filament\Tables\Columns\Column;
use Illuminate\Database\Eloquent\Builder;

/**
 * Add-on-contributed columns on the admin Users list, keyed by name like
 * AccountMenuRegistry so re-registering replaces. Each is a Filament column,
 * built per render so its label follows the locale, plus an optional hook on
 * the list's query to load what the column shows as subqueries, never a
 * query per row. ListUsers puts them after Status. The teams tier registers
 * one: the teams a user is in.
 */
class UserListColumns
{
    /** @var array<string, array{column: Closure(): Column, query: (Closure(Builder): Builder)|null}> */
    private static array $columns = [];

    /**
     * @param  Closure(): Column  $column
     * @param  (Closure(Builder $query): Builder)|null  $query  Adds what the column reads to the Users query.
     */
    public static function register(string $name, Closure $column, ?Closure $query = null): void
    {
        self::$columns[$name] = ['column' => $column, 'query' => $query];
    }

    /** @return list<Column> */
    public static function columns(): array
    {
        return collect(self::$columns)
            ->map(fn (array $entry): Column => ($entry['column'])())
            ->values()
            ->all();
    }

    public static function modifyQuery(Builder $query): Builder
    {
        return collect(self::$columns)
            ->pluck('query')
            ->filter()
            ->reduce(fn (Builder $query, Closure $modify): Builder => $modify($query), $query);
    }

    /** Drop all columns, for tests. */
    public static function flush(): void
    {
        self::$columns = [];
    }
}
