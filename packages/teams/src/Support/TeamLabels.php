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
 * `:member`/`:members`, `:owner`/`:owners`. An acronym label ("HQ", "NGOs")
 * keeps its capitals; see inSentence().
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
     * Replacements for translated strings, lower-cased except acronyms (see
     * inSentence()). Laravel derives the sentence-case forms (`:Team`, `:Members`, …) from a capitalised placeholder.
     *
     * @return array<string, string>
     */
    public static function replacements(): array
    {
        return [
            'team' => self::inSentence(self::singular()),
            'teams' => self::inSentence(self::plural()),
            'member' => self::inSentence(self::member()),
            'members' => self::inSentence(self::memberPlural()),
            'owner' => self::inSentence(self::owner()),
            'owners' => self::inSentence(self::ownerPlural()),
        ];
    }

    /**
     * A label as it reads mid-sentence: each word lower-cased, except an acronym
     * (two or more capitals or digits, optionally a plural "s": HQ, NGOs, 3PL),
     * which keeps its capitals so "HQ" never reads "hq" (#17).
     */
    private static function inSentence(string $label): string
    {
        return (string) preg_replace_callback(
            // \p{M}: an accent stored as a separate mark (decomposed "É") stays part of its letter.
            '/[\p{L}\p{M}\p{N}]+/u',
            fn (array $word): string => preg_match('/^(?:[\p{Lu}\p{N}]\p{M}*){2,}s?$/u', $word[0]) ? $word[0] : Str::lower($word[0]),
            $label,
        );
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
