<?php

declare(strict_types=1);

namespace Concise\Teams\Support;

use Illuminate\Support\Str;

/**
 * The project's words for the tier's domain nouns (config `teams.labels`, scope
 * §8) and the one place they're turned into the placeholders every tier string
 * uses — see team_trans(). Relabelling a team to "salon", its members to
 * "stylists" or its owner to "manager" is a config change; classes, tables and
 * route names stay Team / member / owner on purpose.
 *
 * Placeholders (lower-case here; Laravel sentence-cases `:Team`, `:Members`, …
 * automatically from the lower-case replacement): `:team`/`:teams`,
 * `:member`/`:members`, `:owner`/`:owners`.
 */
final class TeamLabels
{
    public static function singular(): string
    {
        return (string) config('teams.labels.team.singular', 'Team');
    }

    public static function plural(): string
    {
        return (string) config('teams.labels.team.plural', 'Teams');
    }

    public static function member(): string
    {
        return (string) config('teams.labels.member.singular', 'Member');
    }

    public static function memberPlural(): string
    {
        return (string) config('teams.labels.member.plural', 'Members');
    }

    public static function owner(): string
    {
        return (string) config('teams.labels.owner.singular', 'Owner');
    }

    public static function ownerPlural(): string
    {
        return (string) config('teams.labels.owner.plural', 'Owners');
    }

    /**
     * Replacements for translated strings, all lower-case — Laravel derives the
     * sentence-case forms (`:Team`, `:Members`, …) from a capitalised placeholder.
     *
     * @return array<string, string>
     */
    public static function replacements(): array
    {
        return [
            'team' => Str::lower(self::singular()),
            'teams' => Str::lower(self::plural()),
            'member' => Str::lower(self::member()),
            'members' => Str::lower(self::memberPlural()),
            'owner' => Str::lower(self::owner()),
            'owners' => Str::lower(self::ownerPlural()),
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
