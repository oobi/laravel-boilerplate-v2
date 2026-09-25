<?php

declare(strict_types=1);

namespace Concise\Teams\Livewire\Admin;

use App\Enums\SystemPermission;
use App\Models\Role;
use App\Models\User;
use App\Support\Theme\DaisyColor;
use Concise\Teams\Models\Team;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
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

    public function mount(User $user): void
    {
        Gate::authorize(SystemPermission::VIEW_USERS->value);

        $this->user = $user;
    }

    /** The table in a section, titled like the page's other sections (User Information). */
    public function panelSchema(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(team_trans('memberships.title'))
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
                    ->viewData(['linked' => Gate::allows(SystemPermission::VIEW_TEAMS->value)]),

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

        $standing = match (true) {
            $team->isPrimaryOwner($this->user) => [['label' => team_trans('members.primary_owner'), 'color' => DaisyColor::SUCCESS->toFilamentColor()]],
            $team->isOwnedBy($this->user) => [['label' => team_trans('members.owner'), 'color' => DaisyColor::SUCCESS->toFilamentColor()]],
            default => $team->rolesFor($this->user)
                ->map(fn (string $name): array => [
                    'label' => $name,
                    'color' => ($this->roles->get($name)?->badgeColor() ?? DaisyColor::NEUTRAL)->toFilamentColor(),
                ])
                ->whenEmpty(fn () => collect([['label' => team_trans('members.no_role'), 'color' => DaisyColor::NEUTRAL->toFilamentColor()]]))
                ->values()
                ->all(),
        };

        if ($team->isSuspended($this->user)) {
            $standing[] = ['label' => team_trans('members.suspended'), 'color' => DaisyColor::WARNING->toFilamentColor()];
        }

        return $standing;
    }
}
