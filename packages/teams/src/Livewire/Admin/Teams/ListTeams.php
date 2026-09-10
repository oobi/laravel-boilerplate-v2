<?php

declare(strict_types=1);

namespace Concise\Teams\Livewire\Admin\Teams;

use App\Enums\SystemPermission;
use App\Livewire\Concerns\ManagesTrashedRecords;
use App\Models\User;
use App\Support\Theme\DaisyColor;
use Concise\Teams\Models\Team;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Support\Enums\Width;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Component;

/**
 * System admin: every team on the platform (scope §7 "manage all teams").
 * Gated by the `manage teams` system permission, not team membership — this is
 * where admin-provisioned teams are created.
 *
 * Taking a team out of service is a two-step, reversible act: deactivate
 * (members keep their membership, can't enter), then delete (soft — restorable
 * from the trash view), then, if really gone, force-delete from the trash.
 * Delete is only offered for an inactive team.
 */
class ListTeams extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;
    use ManagesTrashedRecords;

    public function mount(): void
    {
        Gate::authorize(SystemPermission::MANAGE_TEAMS->value);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Team::query()->with('owner')->withCount('users'))
            ->defaultSort('team_composite')
            ->columns([
                Tables\Columns\ViewColumn::make('team_composite')
                    ->label(__('Name'))
                    ->view('teams::filament.tables.columns.team-column')
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('name', $direction))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->where(function (Builder $q) use ($search): void {
                        $q->where('name', 'like', "%{$search}%")
                            ->orWhere('slug', 'like', "%{$search}%");
                    })),

                // The owner as a person (avatar, linked name, email) — the user column accepts a related User as its state.
                Tables\Columns\ViewColumn::make('owner')
                    ->label(__('Owner'))
                    ->view('filament.tables.columns.user-column'),

                Tables\Columns\TextColumn::make('users_count')
                    ->label(__('Members'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('active')
                    ->label(__('admin.status'))
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? __('Active') : __('Inactive'))
                    ->color(fn (bool $state): string => ($state ? DaisyColor::SUCCESS : DaisyColor::NEUTRAL)->toFilamentColor()),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Created'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('active')
                    ->label(__('admin.status'))
                    ->options(self::statusOptions())
                    ->modifyFormFieldUsing(fn ($field) => $field->live(debounce: '1ms'))
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        '1' => $query->where('active', true),
                        '0' => $query->where('active', false),
                        default => $query,
                    }),

                TrashedFilter::make(),
            ])
            ->deferFilters(false)
            ->recordActions([
                ActionGroup::make([
                    Action::make('view')
                        ->label(__('admin.view'))
                        ->icon('heroicon-o-eye')
                        ->url(fn (Team $record): string => route('teams.show', $record))
                        ->hidden(fn (Team $record): bool => $record->trashed()),

                    // The reversible way to take a team out of service: members keep their
                    // membership but can't enter it (ResolveTeamContext) until it's reactivated.
                    Action::make('toggleActive')
                        ->label(fn (Team $record): string => $record->active ? __('admin.deactivate') : __('admin.activate'))
                        ->icon(fn (Team $record): string => $record->active ? 'heroicon-o-pause-circle' : 'heroicon-o-check-circle')
                        ->color(fn (Team $record): string => ($record->active ? DaisyColor::WARNING : DaisyColor::SUCCESS)->toFilamentColor())
                        ->requiresConfirmation()
                        ->modalDescription(fn (Team $record): string => $record->active
                            ? __('Deactivate :team? Its members keep their membership but can’t use it until it’s reactivated.', ['team' => $record->name])
                            : __('Reactivate :team? Its members regain access immediately.', ['team' => $record->name]))
                        ->hidden(fn (Team $record): bool => $record->trashed())
                        ->action(function (Team $record): void {
                            Gate::authorize(SystemPermission::MANAGE_TEAMS->value);

                            $record->update(['active' => ! $record->active]);

                            Notification::make()
                                ->title($record->active
                                    ? __(':team activated', ['team' => $record->name])
                                    : __(':team deactivated', ['team' => $record->name]))
                                ->success()
                                ->send();
                        }),

                    // Only an inactive team can be deleted — deactivate first (see class docblock).
                    DeleteAction::make()
                        ->visible(fn (Team $record): bool => ! $record->active)
                        ->modalDescription(fn (Team $record): string => Team::deleteWarning($record))
                        ->using(function (Team $record): void {
                            Gate::authorize(SystemPermission::MANAGE_TEAMS->value);
                            abort_if($record->active, 403, __('Deactivate the :label before deleting it.', ['label' => Str::lower(config('teams.labels.singular', 'Team'))]));

                            $record->delete();
                        }),

                    RestoreAction::make()
                        ->authorize(fn (): bool => Gate::allows(SystemPermission::MANAGE_TEAMS->value)),

                    ForceDeleteAction::make()
                        ->modalDescription(fn (Team $record): string => Team::deleteWarning($record, permanent: true))
                        ->authorize(fn (): bool => Gate::allows(SystemPermission::MANAGE_TEAMS->value)),
                ]),
            ])
            ->searchPlaceholder(__('Search :label…', ['label' => Str::lower(config('teams.labels.plural', 'Teams'))]))
            ->emptyStateHeading(__('No :label found', ['label' => Str::lower(config('teams.labels.plural', 'Teams'))]))
            ->paginated(config('pagination.page_sizes'))
            ->defaultPaginationPageOption(config('pagination.default_page_size'));
    }

    /** Admin-provisioned creation: a name and an owner, who becomes the first member. */
    public function createTeamAction(): Action
    {
        return Action::make('createTeam')
            ->label(__('Add :label', ['label' => config('teams.labels.singular', 'Team')]))
            ->icon('heroicon-o-plus')
            ->modalHeading(__('Add :label', ['label' => config('teams.labels.singular', 'Team')]))
            ->modalWidth(Width::Medium)
            ->schema([
                TextInput::make('name')
                    ->label(__('Name'))
                    ->required()
                    ->maxLength(255),

                Select::make('user_id')
                    ->label(__('Owner'))
                    ->required()
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search): array => $this->searchUsers($search))
                    ->getOptionLabelUsing(fn (mixed $value): ?string => User::find($value)?->email),

                Toggle::make('active')
                    ->label(__('Active'))
                    ->default(true),
            ])
            ->action(function (array $data): void {
                Gate::authorize(SystemPermission::MANAGE_TEAMS->value);

                $team = Team::create([
                    'name' => $data['name'],
                    'user_id' => $data['user_id'],
                    'active' => (bool) $data['active'],
                ]);

                Notification::make()
                    ->title(__(':label created', ['label' => config('teams.labels.singular', 'Team')]))
                    ->success()
                    ->send();

                $this->redirect(route('teams.show', $team));
            });
    }

    /**
     * Options for the status filter (shared with the Blade header's select).
     *
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            '1' => __('Active'),
            '0' => __('Inactive'),
        ];
    }

    protected function emptyTrashPermission(): ?string
    {
        return SystemPermission::MANAGE_TEAMS->value;
    }

    /** @return array<int, string> */
    private function searchUsers(string $search): array
    {
        return User::query()
            ->where(function (Builder $query) use ($search): void {
                $query->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            })
            ->orderBy('last_name')
            ->limit(20)
            ->get()
            ->mapWithKeys(fn (User $user): array => [$user->id => "{$user->name} — {$user->email}"])
            ->all();
    }

    public function render(): View
    {
        return view('teams::livewire.admin.teams.list-teams');
    }
}
