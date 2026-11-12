<?php

declare(strict_types=1);

namespace Concise\Teams\Livewire\Admin;

use App\Enums\SystemPermission;
use App\Models\Role;
use App\Models\User;
use App\Support\Theme\DaisyColor;
use Closure;
use Concise\Teams\Actions\AddUserToTeams;
use Concise\Teams\Enums\TeamSystemPermission;
use Concise\Teams\Models\Membership;
use Concise\Teams\Models\Team;
use Concise\Teams\Support\TeamLabels;
use Concise\Teams\Support\TeamRoleField;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\TableSelect;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The teams a user belongs to, with their standing in each (primary owner,
 * owner, or role badges), paginated. Rendered by TeamMembershipsPanel on the
 * admin User show page; authorized like that page, since pagination requests
 * reach this component directly.
 */
class UserMemberships extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    #[Locked]
    public User $user;

    /** @var array<int, list<array{label: string, color: string}>> */
    private array $badges = [];

    /** @var Collection<string, Role>|null */
    private ?Collection $roles = null;

    /**
     * The user's memberships with their roles, by team id: one query for every row, not three per row.
     *
     * @var Collection<int, Membership>|null
     */
    private ?Collection $memberships = null;

    public function mount(User $user): void
    {
        Gate::authorize(SystemPermission::VIEW_USERS->value);

        $this->user = $user;
    }

    /** The table in a section, titled like the page's other sections (User information). */
    public function panelSchema(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(team_trans('memberships.title'))
                // The Users list's "N teams" links here.
                ->id('team-memberships')
                ->headerActions([$this->addToTeamsAction()])
                ->schema([EmbeddedTable::make()]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Team::query()
                ->whereHas('users', fn (Builder $query): Builder => $query->whereKey($this->user->getKey()))
                ->addSelect(['joined_at' => DB::table('team_user')
                    ->select('created_at')
                    ->whereColumn('team_id', 'teams.id')
                    ->where('user_id', $this->user->getKey()),
                ]))
            ->defaultSort('name')
            ->columns([
                Tables\Columns\ViewColumn::make('name')
                    ->label(team_trans('admin.name'))
                    ->view('teams::filament.tables.columns.membership-team-column')
                    ->width('100%')
                    ->viewData(['linked' => Gate::allows(TeamSystemPermission::VIEW_TEAMS->value)]),

                Tables\Columns\TextColumn::make('standing')
                    ->label(team_trans('members.roles'))
                    ->state(fn (Team $record): array => array_column($this->badgesFor($record), 'label'))
                    ->badge()
                    ->color(fn (string $state, Team $record): string => collect($this->badgesFor($record))->firstWhere('label', $state)['color']),

                Tables\Columns\TextColumn::make('membership_status')
                    ->label(__('admin.status'))
                    ->state(fn (Team $record): array => array_column($this->statusStates($record), 'label'))
                    ->status()
                    ->color(fn (string $state, Team $record): string => collect($this->statusStates($record))->firstWhere('label', $state)['color']),

                Tables\Columns\TextColumn::make('joined_at')
                    ->label(team_trans('memberships.member_since'))
                    ->date()
                    ->width('1%')
                    ->sortable()
                    ->visibleFrom('lg'),
            ])
            // "2 teams selected", in the project's own word for a team.
            ->modelLabel(TeamLabels::replacements()['team'])
            ->pluralModelLabel(TeamLabels::replacements()['teams'])
            // Ownership is changed on the team's ownership screen, never here.
            ->checkIfRecordIsSelectableUsing(fn (Team $record): bool => $this->isManageable($record))
            ->recordActions([
                ActionGroup::make([
                    Action::make('changeRole')
                        ->label(team_trans(Team::allowsMultipleRoles() ? 'members.change_roles' : 'members.change_role'))
                        ->icon('heroicon-o-shield-check')
                        ->modalWidth(Width::Small)
                        ->modalDescription(fn (Team $record): string => team_trans('memberships.change_role_in', ['person' => $this->user->name, 'name' => $record->name]))
                        ->fillForm(fn (Team $record): array => ['roles' => $this->rolesFor($record)])
                        ->schema([TeamRoleField::make()->required()])
                        ->action(fn (Team $record, array $data) => $this->apply(collect([$record]), fn (Team $team) => $team->syncMemberRoles($this->user, (array) TeamRoleField::selected($data)), 'memberships.roles_changed')),

                    Action::make('suspend')
                        ->label(team_trans('members.suspend'))
                        ->icon('heroicon-o-pause-circle')
                        ->color(DaisyColor::WARNING->toFilamentColor())
                        ->requiresConfirmation()
                        ->modalDescription(fn (Team $record): string => team_trans('members.suspend_confirm', ['person' => $this->user->name, 'name' => $record->name]))
                        ->visible(fn (Team $record): bool => ! $this->isSuspendedIn($record))
                        ->action(fn (Team $record) => $this->apply(collect([$record]), fn (Team $team) => $team->suspendMember($this->user), 'memberships.suspended_in')),

                    Action::make('reinstate')
                        ->label(team_trans('members.reinstate'))
                        ->icon('heroicon-o-play-circle')
                        ->color(DaisyColor::SUCCESS->toFilamentColor())
                        ->visible(fn (Team $record): bool => $this->isSuspendedIn($record))
                        ->action(fn (Team $record) => $this->apply(collect([$record]), fn (Team $team) => $team->reinstateMember($this->user), 'memberships.reinstated_in')),

                    Action::make('remove')
                        ->label(team_trans('members.remove'))
                        ->icon('heroicon-o-user-minus')
                        ->color(DaisyColor::ERROR->toFilamentColor())
                        ->requiresConfirmation()
                        ->modalDescription(fn (Team $record): string => team_trans('members.remove_confirm', ['person' => $this->user->name, 'name' => $record->name]))
                        ->action(fn (Team $record) => $this->apply(collect([$record]), fn (Team $team) => $team->removeMember($this->user), 'memberships.removed_from')),
                ])->visible(fn (Team $record): bool => $this->isManageable($record)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('bulkChangeRoles')
                        ->label(team_trans(Team::allowsMultipleRoles() ? 'members.change_roles' : 'members.change_role'))
                        ->icon('heroicon-o-shield-check')
                        ->modalWidth(Width::Small)
                        ->schema([TeamRoleField::make()->required()])
                        ->action(fn (Collection $records, array $data) => $this->apply($records, fn (Team $team) => $team->syncMemberRoles($this->user, (array) TeamRoleField::selected($data)), 'memberships.roles_changed'))
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('bulkSuspend')
                        ->label(team_trans('members.suspend'))
                        ->icon('heroicon-o-pause-circle')
                        ->color(DaisyColor::WARNING->toFilamentColor())
                        ->requiresConfirmation()
                        ->modalDescription(team_trans('memberships.suspend_many_confirm', ['person' => $this->user->name]))
                        ->action(fn (Collection $records) => $this->apply($records->reject(fn (Team $team): bool => $this->isSuspendedIn($team)), fn (Team $team) => $team->suspendMember($this->user), 'memberships.suspended_in'))
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('bulkReinstate')
                        ->label(team_trans('members.reinstate'))
                        ->icon('heroicon-o-play-circle')
                        ->color(DaisyColor::SUCCESS->toFilamentColor())
                        ->action(fn (Collection $records) => $this->apply($records->filter(fn (Team $team): bool => $this->isSuspendedIn($team)), fn (Team $team) => $team->reinstateMember($this->user), 'memberships.reinstated_in'))
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('bulkRemove')
                        ->label(team_trans('memberships.remove_from'))
                        ->icon('heroicon-o-user-minus')
                        ->color(DaisyColor::ERROR->toFilamentColor())
                        ->requiresConfirmation()
                        ->modalDescription(team_trans('memberships.remove_many_confirm', ['person' => $this->user->name]))
                        ->action(fn (Collection $records) => $this->apply($records, fn (Team $team) => $team->removeMember($this->user), 'memberships.removed_from'))
                        ->deselectRecordsAfterCompletion(),
                ])->visible(fn (): bool => Gate::allows(TeamSystemPermission::MANAGE_TEAMS->value)),
            ])
            ->emptyStateHeading(team_trans('memberships.none'));
    }

    /**
     * Add the user to several teams at once, in one slide-over: choose the role,
     * tick the teams in a table (teams they aren't in, active ones by default).
     * Admin-only, like a team's own "Add member", so it checks `manage teams`
     * directly.
     */
    public function addToTeamsAction(): Action
    {
        return Action::make('addToTeams')
            ->label(team_trans('memberships.add'))
            ->icon('heroicon-o-user-plus')
            ->modalHeading(team_trans('memberships.add_heading', ['person' => $this->user->name]))
            ->slideOver()
            ->modalWidth(Width::TwoExtraLarge)
            ->modalSubmitActionLabel(team_trans('memberships.add_submit'))
            ->visible(fn (): bool => Gate::allows(TeamSystemPermission::MANAGE_TEAMS->value))
            ->schema([
                TeamRoleField::make(),

                TableSelect::make('teams')
                    ->label(team_trans('memberships.teams_field'))
                    ->multiple()
                    ->required()
                    ->tableConfiguration(TeamPickerTable::class)
                    ->tableArguments(['user' => $this->user->getKey()])
                    // Every id must be a live team (the picker's list is only presentational); AddUserToTeams then skips teams the user is already in.
                    ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                        // A forged id that isn't a whole number fails here, before it reaches the query (Postgres rejects id IN ('abc')).
                        if (collect(Arr::wrap($value))->contains(fn (mixed $id): bool => ! is_scalar($id) || ! ctype_digit((string) $id))) {
                            $fail(team_trans('memberships.teams_gone'));

                            return;
                        }

                        $ids = array_unique(Arr::wrap($value));

                        if (Team::query()->whereKey($ids)->count() !== count($ids)) {
                            $fail(team_trans('memberships.teams_gone'));
                        }
                    }),
            ])
            ->action(function (array $data): void {
                Gate::authorize(TeamSystemPermission::MANAGE_TEAMS->value);

                $added = app(AddUserToTeams::class)($this->user, $data['teams'], TeamRoleField::selected($data));
                $this->badges = [];
                $this->memberships = null;

                $added->isEmpty()
                    ? Notification::make()->title(team_trans('memberships.none_added', ['person' => $this->user->name]))->warning()->send()
                    : Notification::make()->title(team_trans_choice('memberships.added', $added->count(), ['person' => $this->user->name, 'count' => $added->count()]))->success()->send();
            });
    }

    public function render(): View
    {
        return view('teams::livewire.admin.user-memberships');
    }

    /** @return list<array{label: string, color: string}> */
    private function badgesFor(Team $team): array
    {
        return $this->badges[$team->getKey()] ??= $this->resolveBadges($team);
    }

    /** @return list<array{label: string, color: string}> */
    private function resolveBadges(Team $team): array
    {
        $this->roles ??= Team::availableRoles()->get()->keyBy('name');
        $membership = $this->membershipIn($team);

        $standing = match (true) {
            $team->isPrimaryOwner($this->user) => [['label' => team_trans('members.primary_owner'), 'color' => DaisyColor::SUCCESS->toFilamentColor()]],
            (bool) $membership?->is_owner => [['label' => team_trans('members.owner'), 'color' => DaisyColor::SUCCESS->toFilamentColor()]],
            default => ($membership?->roles ?? collect())->sortBy('name')->pluck('name')
                ->map(fn (string $name): array => [
                    'label' => $name,
                    'color' => ($this->roles->get($name)?->badgeColor() ?? DaisyColor::NEUTRAL)->toFilamentColor(),
                ])
                ->whenEmpty(fn () => collect([['label' => team_trans('members.no_role'), 'color' => DaisyColor::NEUTRAL->toFilamentColor()]]))
                ->values()
                ->all(),
        };

        return $standing;
    }

    /**
     * The membership's own state: active, or suspended in this team. (An
     * inactive team is flagged beside its name.)
     *
     * @return list<array{label: string, color: string}>
     */
    private function statusStates(Team $team): array
    {
        return [$this->isSuspendedIn($team)
            ? ['label' => team_trans('members.suspended'), 'color' => DaisyColor::WARNING->toFilamentColor()]
            : ['label' => team_trans('admin.active'), 'color' => DaisyColor::SUCCESS->toFilamentColor()]];
    }

    /** The user's membership of a team, from the one query that loads them all. */
    private function membershipIn(Team $team): ?Membership
    {
        $this->memberships ??= Membership::query()
            ->where('user_id', $this->user->getKey())
            ->with('roles')
            ->get()
            ->keyBy('team_id');

        return $this->memberships->get($team->getKey());
    }

    private function isSuspendedIn(Team $team): bool
    {
        return $this->membershipIn($team)?->suspended_at !== null;
    }

    /** @return string|list<string>|null the user's current role(s) in the team, shaped for TeamRoleField */
    private function rolesFor(Team $team): string|array|null
    {
        $roles = $this->membershipIn($team)?->roles->pluck('name')->all() ?? [];

        return Team::allowsMultipleRoles() ? $roles : ($roles[0] ?? null);
    }

    /**
     * Whether this membership can be changed here: `manage teams`, and not an
     * owner's (ownership is changed on the team's ownership screen).
     */
    private function isManageable(Team $team): bool
    {
        return Gate::allows(TeamSystemPermission::MANAGE_TEAMS->value)
            && ! $team->isPrimaryOwner($this->user)
            && ! $this->membershipIn($team)?->is_owner;
    }

    /**
     * Apply a change to the user's membership of each team, re-checking each
     * here (the selection comes from the browser), then refresh and say how
     * many it changed.
     *
     * @param  BaseCollection<int, Team>  $teams
     * @param  Closure(Team): void  $change
     */
    private function apply(BaseCollection $teams, Closure $change, string $notice): void
    {
        Gate::authorize(TeamSystemPermission::MANAGE_TEAMS->value);

        $changed = $teams->filter(fn (Team $team): bool => $this->isManageable($team));

        // E.g. Reinstate with none of the selection suspended, or only owned teams chosen.
        if ($changed->isEmpty()) {
            Notification::make()->title(team_trans('memberships.nothing_to_change'))->warning()->send();

            return;
        }

        DB::transaction(fn () => $changed->each($change));

        $this->badges = [];
        $this->memberships = null;

        Notification::make()
            ->title(team_trans_choice($notice, $changed->count(), ['person' => $this->user->name, 'count' => $changed->count()]))
            ->success()
            ->send();
    }
}
