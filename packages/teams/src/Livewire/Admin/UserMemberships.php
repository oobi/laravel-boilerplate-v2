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
use Concise\Teams\Support\TeamRoleField;
use Filament\Actions\Action;
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

                Tables\Columns\TextColumn::make('joined_at')
                    ->label(team_trans('memberships.member_since'))
                    ->date()
                    ->width('1%')
                    ->sortable(),
            ])
            ->emptyStateHeading(team_trans('memberships.none'))
            ->paginated(config('pagination.page_sizes'))
            ->defaultPaginationPageOption(config('pagination.default_page_size'));
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
        $this->memberships ??= Membership::query()
            ->where('user_id', $this->user->getKey())
            ->with('roles')
            ->get()
            ->keyBy('team_id');

        /** @var Membership|null $membership */
        $membership = $this->memberships->get($team->getKey());

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

        if ($membership?->suspended_at !== null) {
            $standing[] = ['label' => team_trans('members.suspended'), 'color' => DaisyColor::WARNING->toFilamentColor()];
        }

        return $standing;
    }
}
