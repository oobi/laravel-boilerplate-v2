<?php

declare(strict_types=1);

namespace Concise\Teams\Livewire\Admin;

use App\Support\Theme\DaisyColor;
use Concise\Teams\Livewire\Admin\Teams\ListTeams;
use Concise\Teams\Models\Team;
use Concise\Teams\Support\TeamLabels;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The teams table in UserMemberships' "Add to teams" slide-over (a
 * TableSelect field): the teams a user isn't in yet, searchable, active ones
 * first by default. Which teams it lists is only presentational; the
 * AddUserToTeams action re-checks what's submitted.
 */
class TeamPickerTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->query(Team::query())
            // The user's id arrives as a table argument, attached after configure() runs, so it's read here, per query.
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereDoesntHave(
                'users',
                fn (Builder $users): Builder => $users->whereKey($table->getArguments()['user'] ?? null),
            ))
            ->defaultSort('name')
            // "2 teams selected", in the project's own word for a team.
            ->modelLabel(TeamLabels::replacements()['team'])
            ->pluralModelLabel(TeamLabels::replacements()['teams'])
            ->columns([
                TextColumn::make('name')
                    ->label(team_trans('admin.name'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('active')
                    ->label(__('admin.status'))
                    ->status()
                    ->formatStateUsing(fn (bool $state): string => $state ? team_trans('admin.active') : team_trans('admin.inactive'))
                    ->color(fn (bool $state): string => ($state ? DaisyColor::SUCCESS : DaisyColor::NEUTRAL)->toFilamentColor()),
            ])
            ->filters([
                SelectFilter::make('active')
                    ->label(__('admin.status'))
                    ->options(ListTeams::statusOptions())
                    ->default('1')
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        '1' => $query->where('active', true),
                        '0' => $query->where('active', false),
                        default => $query,
                    }),
            ])
            ->paginated(config('pagination.page_sizes'))
            ->defaultPaginationPageOption(config('pagination.default_page_size'));
    }
}
