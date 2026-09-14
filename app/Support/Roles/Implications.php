<?php

declare(strict_types=1);

namespace App\Support\Roles;

/**
 * Closes a selection of permission names over a RoleScope::implications()
 * map. The map is declared one hop at a time ("delete users carries view
 * users", "view users carries access admin panel") and the closure follows the
 * chain, so a write path stores everything a grant needs to be reachable and
 * the Roles form ticks and locks the whole chain, not just the first hop.
 * Order is preserved (each name followed by what it carries), no duplicates;
 * a cycle in the map terminates.
 */
final class Implications
{
    /**
     * @param  array<string, list<string>>  $map  permission name => the names it carries
     * @param  list<string>  $selected
     * @return list<string> $selected plus everything it implies, transitively
     */
    public static function close(array $map, array $selected): array
    {
        $closed = [];
        $queue = array_values($selected);

        while ($queue !== []) {
            $name = array_shift($queue);

            if (in_array($name, $closed, true)) {
                continue;
            }

            $closed[] = $name;

            foreach ($map[$name] ?? [] as $implied) {
                $queue[] = $implied;
            }
        }

        return $closed;
    }

    /**
     * What the selection carries but doesn't yet contain — the rows a stored
     * role is missing after an implication is added (the sync command).
     *
     * @param  array<string, list<string>>  $map
     * @param  list<string>  $selected
     * @return list<string>
     */
    public static function impliedBy(array $map, array $selected): array
    {
        return array_values(array_diff(self::close($map, $selected), $selected));
    }

    /**
     * Whether some OTHER selected name carries this one — the form locks such
     * an option while its implier is ticked. The option itself is normally
     * already in the selection (ticked along with its implier), so this asks
     * about the selection without it.
     *
     * @param  array<string, list<string>>  $map
     * @param  list<string>  $selected
     */
    public static function isCarried(array $map, array $selected, string $name): bool
    {
        return in_array($name, self::close($map, array_values(array_diff($selected, [$name]))), true);
    }
}
