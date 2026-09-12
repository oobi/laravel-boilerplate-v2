<?php

declare(strict_types=1);

namespace Concise\Teams\Livewire\Team;

use App\Enums\SystemPermission;
use App\Models\Role;
use App\Models\User;
use App\Support\Theme\DaisyColor;
use Concise\Teams\Enums\TeamAbility;
use Concise\Teams\Models\Team;
use Concise\Teams\Support\TeamRoleField;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Support\Enums\Width;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * A team's members — searchable, filterable (role, status), paginated —
 * with per-row role change, ownership (make/revoke co-owner, transfer primary
 * ownership), suspension and removal. Shared by the team area (Members page)
 * and the system admin (team Members tab). Membership actions are open to
 * either audience — the `manage teams` system permission, or the team's
 * manage-members ability (any owner, or permission holder); ownership actions
 * to the primary owner or a system admin. Never enters the team context — the
 * Team methods scope themselves — so a system admin's own permission checks
 * stay in the system scope.
 */
class MembersTable extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    /** The role filter's entry for owners, who hold no role (cf. ListUsers' super-admin entry). */
    public const OWNERS_FILTER_VALUE = '__owners__';

    #[Locked]
    public Team $team;

    /** @var SupportCollection<int, list<string>>|null */
    private ?SupportCollection $memberRoles = null;

    /** @var Collection<string, Role>|null */
    private ?Collection $teamRoles = null;

    /** @var SupportCollection<int, int>|null */
    private ?SupportCollection $ownerIds = null;

    /** @var SupportCollection<int, int>|null */
    private ?SupportCollection $suspendedIds = null;

    public function mount(Team $team): void
    {
        $this->team = $team;

        abort_unless($this->canView(), 403);
    }

    /** Re-query after the host page adds a member (or this table changes one). */
    #[On('team-members-updated')]
    public function refreshMembers(): void {}

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => User::query()
                ->whereHas('teams', fn (Builder $query) => $query->whereKey($this->team->getKey())))
            ->defaultSort('last_name')
            ->columns([
                Tables\Columns\ViewColumn::make('user_composite')
                    ->label(__('admin.user'))
                    ->view('filament.tables.columns.user-column')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->where(function (Builder $q) use ($search): void {
                        $q->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })),

                Tables\Columns\TextColumn::make('team_role')
                    ->label(Team::allowsMultipleRoles() ? team_trans('members.roles') : team_trans('members.role'))
                    ->getStateUsing(fn (User $record): array => $this->badgesFor($record))
                    ->badge()
                    ->color(fn (string $state): string => $this->badgeColor($state)),
            ])
            ->filters([
                // Mirrors the Users list: a "role" filter (with owners as the structural entry, like
                // Super Admin there) and a "status" filter.
                Tables\Filters\SelectFilter::make('role')
                    ->label(team_trans('members.role'))
                    ->options(fn (): array => $this->roleFilterOptions())
                    ->modifyFormFieldUsing(fn ($field) => $field->live(debounce: '1ms'))
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        null, '' => $query,
                        self::OWNERS_FILTER_VALUE => $query->whereIn('users.id', $this->ownerIds()),
                        default => $this->whereHoldsRole($query, (string) $data['value']),
                    }),

                Tables\Filters\SelectFilter::make('status')
                    ->label(__('admin.status'))
                    ->options(self::statusOptions())
                    ->modifyFormFieldUsing(fn ($field) => $field->live(debounce: '1ms'))
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'active' => $query->whereHas('teams', fn (Builder $q) => $q->whereKey($this->team->getKey())->whereNull('team_user.suspended_at')),
                        'suspended' => $query->whereHas('teams', fn (Builder $q) => $q->whereKey($this->team->getKey())->whereNotNull('team_user.suspended_at')),
                        default => $query,
                    }),
            ])
            ->deferFilters(false)
            ->recordActions([
                ActionGroup::make([
                    Action::make('changeRole')
                        ->label(Team::allowsMultipleRoles() ? team_trans('members.change_roles') : team_trans('members.change_role'))
                        ->icon('heroicon-o-shield-check')
                        ->modalWidth(Width::Small)
                        // An owner's/co-owner's role is protected (the shield): only
                        // someone who can manage owners (the primary owner, or a system
                        // admin) may change it, so a plain manage-members holder can't
                        // strip it. A regular member's role needs manage-members.
                        ->visible(fn (User $record): bool => $this->canActOn($record))
                        ->fillForm(fn (User $record): array => [
                            'roles' => Team::allowsMultipleRoles()
                                ? $this->memberRoles()->get($record->id, [])
                                : ($this->memberRoles()->get($record->id, [])[0] ?? null),
                        ])
                        ->schema([TeamRoleField::make()->required()])
                        ->action(function (User $record, array $data): void {
                            abort_unless($this->canActOn($record), 403);

                            $this->team->syncMemberRoles($record, TeamRoleField::selected($data));
                            $this->memberRoles = null;

                            Notification::make()->title(team_trans('members.roles_updated'))->success()->send();
                        }),

                    Action::make('makeOwner')
                        ->label(team_trans('members.make_owner'))
                        ->icon('heroicon-o-key')
                        ->requiresConfirmation()
                        ->modalDescription(fn (User $record): string => team_trans('members.make_owner_confirm', ['person' => $record->name, 'name' => $this->team->name]))
                        ->visible(fn (User $record): bool => $this->canManageOwners() && ! $this->isOwner($record))
                        ->action(function (User $record): void {
                            abort_unless($this->canManageOwners(), 403);

                            $this->team->makeOwner($record);
                            $this->ownerIds = null;
                            $this->dispatch('team-members-updated');

                            Notification::make()->title(team_trans('members.made_owner', ['person' => $record->name]))->success()->send();
                        }),

                    Action::make('revokeOwner')
                        ->label(team_trans('members.revoke_owner'))
                        ->icon('heroicon-o-key')
                        ->color(DaisyColor::WARNING->toFilamentColor())
                        ->requiresConfirmation()
                        ->modalDescription(fn (User $record): string => team_trans('members.revoke_owner_confirm', ['person' => $record->name, 'name' => $this->team->name]))
                        ->visible(fn (User $record): bool => $this->canManageOwners() && $this->isOwner($record) && ! $this->team->isPrimaryOwner($record))
                        ->action(function (User $record): void {
                            abort_unless($this->canManageOwners(), 403);

                            $this->team->revokeOwner($record);
                            $this->ownerIds = null;
                            $this->dispatch('team-members-updated');

                            Notification::make()->title(team_trans('members.revoked_owner', ['person' => $record->name]))->success()->send();
                        }),

                    Action::make('transferOwnership')
                        ->label(team_trans('members.transfer'))
                        ->icon('heroicon-o-arrow-right-circle')
                        ->color(DaisyColor::WARNING->toFilamentColor())
                        ->requiresConfirmation()
                        ->modalDescription(fn (User $record): string => team_trans('members.transfer_confirm', ['person' => $record->name, 'name' => $this->team->name]))
                        ->visible(fn (User $record): bool => $this->canTransferOwnership() && ! $this->team->isPrimaryOwner($record))
                        ->action(function (User $record): void {
                            abort_unless($this->canTransferOwnership(), 403);

                            $this->team->transferOwnership($record);
                            $this->ownerIds = null;
                            $this->dispatch('team-members-updated');

                            Notification::make()->title(team_trans('members.transferred', ['person' => $record->name]))->success()->send();
                        }),

                    // The team-level counterpart of deactivating an account: access withheld, everything else kept.
                    Action::make('suspend')
                        ->label(team_trans('members.suspend'))
                        ->icon('heroicon-o-pause-circle')
                        ->color(DaisyColor::WARNING->toFilamentColor())
                        ->requiresConfirmation()
                        ->modalDescription(fn (User $record): string => team_trans('members.suspend_confirm', ['person' => $record->name, 'name' => $this->team->name]))
                        ->visible(fn (User $record): bool => ! $this->isSuspended($record)
                            && ! $this->team->isPrimaryOwner($record)
                            && $this->canActOn($record))
                        ->action(function (User $record): void {
                            abort_unless($this->canActOn($record), 403);

                            $this->team->suspendMember($record);
                            $this->suspendedIds = null;
                            $this->dispatch('team-members-updated');

                            Notification::make()->title(team_trans('members.suspended_notice', ['person' => $record->name]))->success()->send();
                        }),

                    Action::make('reinstate')
                        ->label(team_trans('members.reinstate'))
                        ->icon('heroicon-o-play-circle')
                        ->color(DaisyColor::SUCCESS->toFilamentColor())
                        ->visible(fn (User $record): bool => $this->isSuspended($record)
                            && $this->canActOn($record))
                        ->action(function (User $record): void {
                            abort_unless($this->canActOn($record), 403);

                            $this->team->reinstateMember($record);
                            $this->suspendedIds = null;
                            $this->dispatch('team-members-updated');

                            Notification::make()->title(team_trans('members.reinstated', ['person' => $record->name]))->success()->send();
                        }),

                    Action::make('remove')
                        ->label(team_trans('members.remove'))
                        ->icon('heroicon-o-user-minus')
                        ->color(DaisyColor::ERROR->toFilamentColor())
                        ->requiresConfirmation()
                        ->modalDescription(fn (User $record): string => team_trans('members.remove_confirm', ['person' => $record->name, 'name' => $this->team->name]))
                        // Never the primary owner; a co-owner only by someone who could demote them.
                        ->hidden(fn (User $record): bool => $this->team->isPrimaryOwner($record)
                            || ! $this->canActOn($record))
                        ->action(function (User $record): void {
                            abort_if($this->team->isPrimaryOwner($record) || ! $this->canActOn($record), 403);

                            $this->team->removeMember($record);
                            $this->ownerIds = null;
                            $this->dispatch('team-members-updated');

                            Notification::make()->title(team_trans('members.removed'))->success()->send();
                        }),
                ])->visible(fn (): bool => $this->canManage() || $this->canManageOwners() || $this->canTransferOwnership()),
            ])
            ->searchPlaceholder(team_trans('members.search'))
            ->emptyStateHeading(team_trans('members.empty'))
            ->paginated(config('pagination.page_sizes'))
            ->defaultPaginationPageOption(config('pagination.default_page_size'));
    }

    /**
     * System admin only: add an existing account as a member (the team area
     * invites by email instead). Lives in the table's toolbar — a table's
     * primary action belongs beside the table, not in a row of its own.
     */
    public function addMemberAction(): Action
    {
        return Action::make('addMember')
            ->label(team_trans('members.add'))
            ->icon('heroicon-o-user-plus')
            ->modalHeading(team_trans('members.add_heading', ['name' => $this->team->name]))
            ->modalWidth(Width::Medium)
            ->visible(fn (): bool => Gate::allows(SystemPermission::MANAGE_TEAMS->value))
            ->schema([
                Select::make('user_id')
                    ->label(__('admin.user'))
                    ->required()
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search): array => $this->searchNonMembers($search))
                    ->getOptionLabelUsing(fn (mixed $value): ?string => User::find($value)?->email),

                TeamRoleField::make(),
            ])
            ->action(function (array $data): void {
                Gate::authorize(SystemPermission::MANAGE_TEAMS->value);

                $user = User::query()->findOrFail($data['user_id']);
                $this->team->addMember($user, TeamRoleField::selected($data));
                $this->memberRoles = null;
                $this->dispatch('team-members-updated');

                Notification::make()
                    ->title(team_trans('members.added', ['person' => $user->name, 'name' => $this->team->name]))
                    ->success()
                    ->send();
            });
    }

    public function render(): View
    {
        return view('teams::livewire.team.members-table');
    }

    /** @return array<int, string> */
    private function searchNonMembers(string $search): array
    {
        return User::query()
            ->whereDoesntHave('teams', fn (Builder $query) => $query->whereKey($this->team->getKey()))
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

    /**
     * Options for the status filter (shared with the Blade header's select).
     *
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            'active' => team_trans('members.active'),
            'suspended' => team_trans('members.suspended'),
        ];
    }

    /**
     * Options for the role filter: owners first (structural, not a role), then the team roles.
     *
     * @return array<string, string>
     */
    public function roleFilterOptions(): array
    {
        return [
            self::OWNERS_FILTER_VALUE => team_trans('members.owners'),
            ...$this->roleOptions(),
        ];
    }

    /** @return array<string, string> */
    public function roleOptions(): array
    {
        return $this->teamRoles()->map(fn (Role $role): string => $role->name)->all();
    }

    /** May the viewer see the roster at all? (Managing implies viewing; owners always can.) */
    private function canView(): bool
    {
        return Gate::allows(SystemPermission::MANAGE_TEAMS->value)
            || Gate::allows(TeamAbility::VIEW_MEMBERS, $this->team);
    }

    private function canManage(): bool
    {
        return Gate::allows(SystemPermission::MANAGE_TEAMS->value)
            || Gate::allows(TeamAbility::MANAGE_MEMBERS, $this->team);
    }

    /**
     * May the current actor manage this particular member? An owner (primary or
     * co-) is shielded — only manage-owners authority (primary owner / system
     * admin) may touch them; a regular member needs manage-members. Used for
     * role change, suspend/reinstate and removal.
     */
    private function canActOn(User $member): bool
    {
        return $this->isOwner($member) ? $this->canManageOwners() : $this->canManage();
    }

    private function canManageOwners(): bool
    {
        return Gate::allows(SystemPermission::MANAGE_TEAMS->value)
            || Gate::allows(TeamAbility::MANAGE_OWNERS, $this->team);
    }

    private function canTransferOwnership(): bool
    {
        return Gate::allows(SystemPermission::MANAGE_TEAMS->value)
            || Gate::allows(TeamAbility::TRANSFER_OWNERSHIP, $this->team);
    }

    /** Members holding the named role in this team (read from the pivot, outside the team scope). */
    private function whereHoldsRole(Builder $query, string $role): Builder
    {
        $roles = (new Role)->getTable();
        $pivot = config('permission.table_names.model_has_roles');
        $teamKey = config('permission.column_names.team_foreign_key', 'team_id');
        $morphKey = config('permission.column_names.model_morph_key', 'model_id');

        return $query->whereIn('users.id', fn (QueryBuilder $sub) => $sub
            ->select("{$pivot}.{$morphKey}")
            ->from($pivot)
            ->join($roles, "{$roles}.id", '=', "{$pivot}.role_id")
            ->where("{$pivot}.{$teamKey}", $this->team->getKey())
            ->where("{$pivot}.model_type", (new User)->getMorphClass())
            ->where("{$roles}.name", $role));
    }

    private function isOwner(User $member): bool
    {
        return $this->ownerIds()->contains($member->id);
    }

    /** @return SupportCollection<int, int> */
    private function ownerIds(): SupportCollection
    {
        return $this->ownerIds ??= $this->team->ownerIds();
    }

    private function isSuspended(User $member): bool
    {
        return $this->suspendedIds()->contains($member->id);
    }

    /** @return SupportCollection<int, int> */
    private function suspendedIds(): SupportCollection
    {
        return $this->suspendedIds ??= $this->team->users()->wherePivotNotNull('suspended_at')->pluck('users.id');
    }

    /** @return SupportCollection<int, list<string>> */
    private function memberRoles(): SupportCollection
    {
        return $this->memberRoles ??= $this->team->memberRoles();
    }

    /** @return Collection<string, Role> */
    private function teamRoles(): Collection
    {
        return $this->teamRoles ??= Team::availableRoles()->orderBy('name')->get()->keyBy('name');
    }

    /**
     * The badges for a member: their standing (ownership, else roles, else "No role"), plus "Suspended" when they are.
     *
     * @return list<string>
     */
    private function badgesFor(User $member): array
    {
        $badges = [];

        // Owner status is shown ALONGSIDE the role, not instead of it — the badge
        // marks protected owner standing, the role shows actual authority (which,
        // under the managed ownership model, is where an owner's power comes from).
        if ($this->team->isPrimaryOwner($member)) {
            $badges[] = team_trans('members.primary_owner');
        } elseif ($this->isOwner($member)) {
            $badges[] = team_trans('members.owner');
        }

        $roles = $this->memberRoles()->get($member->id, []);

        if ($roles !== []) {
            $badges = [...$badges, ...$roles];
        } elseif ($badges === []) {
            $badges[] = team_trans('members.no_role');
        }

        if ($this->isSuspended($member)) {
            $badges[] = team_trans('members.suspended');
        }

        return $badges;
    }

    private function badgeColor(string $badge): string
    {
        if ($badge === team_trans('members.suspended')) {
            return DaisyColor::WARNING->toFilamentColor();
        }

        if (in_array($badge, [team_trans('members.primary_owner'), team_trans('members.owner')], true)) {
            return DaisyColor::SUCCESS->toFilamentColor();
        }

        return ($this->teamRoles()->get($badge)?->badgeColor() ?? DaisyColor::NEUTRAL)->toFilamentColor();
    }
}
