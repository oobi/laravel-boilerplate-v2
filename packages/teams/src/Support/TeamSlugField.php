<?php

declare(strict_types=1);

namespace Concise\Teams\Support;

use Closure;
use Concise\Teams\Models\Team;
use Filament\Forms\Components\TextInput;

/**
 * The slug field both Settings forms share (member and admin), so the rules
 * live in one place (GitHub oobi#29): lower-cased as typed, a valid DNS label
 * (Team::SLUG_PATTERN, at most 63 characters) and not reserved
 * (DomainPolicy::isReservedSlug), in every mode so switching host mode on
 * later can't strand a team, and unique.
 */
final class TeamSlugField
{
    public static function make(string $helperText): TextInput
    {
        return TextInput::make('slug')
            ->label(team_trans('admin.slug'))
            ->helperText($helperText)
            ->required()
            // Only text is tidied; anything else (a tampered payload) fails the rules below.
            ->mutateStateForValidationUsing(fn (mixed $state): mixed => is_string($state) ? Team::normalizeSlug($state) : $state)
            ->dehydrateStateUsing(fn (mixed $state): mixed => is_string($state) ? Team::normalizeSlug($state) : $state)
            ->maxLength(Team::SLUG_MAX_LENGTH)
            ->regex(Team::SLUG_PATTERN)
            ->validationMessages(['regex' => team_trans('settings.slug_format')])
            // A reserved slug would never route once the slug is a subdomain (TeamHostResolver).
            ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                if (is_string($value) && DomainPolicy::isReservedSlug($value)) {
                    $fail(team_trans('settings.slug_reserved'));
                }
            })
            ->unique(table: 'teams', column: 'slug', ignoreRecord: true);
    }
}
