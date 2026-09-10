<?php

declare(strict_types=1);

namespace Concise\Teams\Support;

use Illuminate\Support\Str;

/**
 * The project's word for a team (config `teams.labels`, scope §8) and the
 * one place it's turned into the `:team` / `:teams` placeholders every tier
 * string uses — see team_trans(). Relabelling to "salon" is a config change;
 * classes, tables and route names stay `Team` on purpose.
 */
final class TeamLabels
{
    public static function singular(): string
    {
        return (string) config('teams.labels.singular', 'Team');
    }

    public static function plural(): string
    {
        return (string) config('teams.labels.plural', 'Teams');
    }

    /**
     * Replacements for translated strings: `:team`/`:teams` lower-case, and
     * (Laravel's own rule) `:Team`/`:Teams` sentence-case.
     *
     * @return array<string, string>
     */
    public static function replacements(): array
    {
        return [
            'team' => Str::lower(self::singular()),
            'teams' => Str::lower(self::plural()),
        ];
    }

    public static function trans(string $key, array $replace = []): string
    {
        return (string) __('teams::teams.'.$key, [...self::replacements(), ...$replace]);
    }

    public static function transChoice(string $key, int $number, array $replace = []): string
    {
        return trans_choice('teams::teams.'.$key, $number, [...self::replacements(), ...$replace]);
    }
}
