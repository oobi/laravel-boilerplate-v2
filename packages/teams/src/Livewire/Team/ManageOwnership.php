<?php

declare(strict_types=1);

namespace Concise\Teams\Livewire\Team;

use App\Enums\SystemPermission;
use App\Models\User;
use App\Support\Filament\AdminAction;
use App\Support\Theme\DaisyColor;
use Closure;
use Concise\Teams\Enums\TeamAbility;
use Concise\Teams\Models\Team;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * A team's ownership — who the primary owner is, who the co-owners are, and
 * the two non-delegable acts: managing co-owners and transferring primary
 * ownership. Shared by the team-area Settings page and the system admin's
 * team Settings tab, so the primary owner can always reach these acts
 * regardless of which role (if any) they hold — the responsibility floor
 * behind TeamPolicy::viewSettings(). Deleting the team is the host page's
 * own action, since the admin version carries a deactivate-first rule.
 *
 * Members are picked with a server-searched select (a team can have
 * hundreds), the same control the admin's "Add member" uses; co-owners are
 * few, so they're listed with a remove action each.
 *
 * @property Team $team
 */
class ManageOwnership extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    #[Locked]
    public Team $team;

    public function mount(Team $team): void
    {
        $this->team = $team;

        abort_unless($this->canManageOwners(), 403);
    }

    /** Re-read owners after the members table changes someone. */
    #[On('team-members-updated')]
    public function refreshOwners(): void {}

    /** The primary owner (responsibility anchor) or a system admin. */
    public function canManageOwners(): bool
    {
        return Gate::allows(SystemPermission::MANAGE_TEAMS->value)
            || Gate::allows(TeamAbility::MANAGE_OWNERS, $this->team);
    }

    public function canTransferOwnership(): bool
    {
        return Gate::allows(SystemPermission::MANAGE_TEAMS->value)
            || Gate::allows(TeamAbility::TRANSFER_OWNERSHIP, $this->team);
    }

    public function primaryOwner(): User
    {
        return $this->team->owner;
    }

    /** @return Collection<int, User> */
    public function coOwners(): Collection
    {
        return $this->team->users()
            ->wherePivot('is_owner', true)
            ->whereKeyNot($this->team->user_id)
            ->orderBy('last_name')
            ->get();
    }

    public function addCoOwnerAction(): Action
    {
        return AdminAction::make('addCoOwner')
            ->label(team_trans('ownership.add_co_owner'))
            ->icon('heroicon-o-key')
            ->soft()
            ->visible(fn (): bool => $this->canManageOwners())
            ->modalHeading(team_trans('ownership.add_co_owner'))
            ->modalDescription(team_trans('ownership.co_owner_help'))
            ->modalWidth(Width::Medium)
            ->schema([
                $this->memberPicker('user_id', team_trans('ownership.co_owner'), excludeOwners: true),
            ])
            ->action(function (array $data): void {
                abort_unless($this->canManageOwners(), 403);

                $user = $this->members(excludeOwners: true)->whereKey($data['user_id'])->firstOrFail();

                $this->team->makeOwner($user);
                $this->dispatch('team-members-updated');

                Notification::make()
                    ->title(team_trans('ownership.co_owner_added', ['person' => $user->name]))
                    ->success()
                    ->send();
            });
    }

    /** Rendered once per listed co-owner with `['user' => $id]` as its arguments. */
    public function removeCoOwnerAction(): Action
    {
        return Action::make('removeCoOwner')
            ->label(team_trans('ownership.remove_co_owner'))
            ->icon('heroicon-o-x-mark')
            ->iconButton()
            ->color(DaisyColor::ERROR->toFilamentColor())
            ->size('sm')
            ->visible(fn (): bool => $this->canManageOwners())
            ->requiresConfirmation()
            ->modalHeading(team_trans('ownership.remove_co_owner'))
            ->modalDescription(fn (array $arguments): string => team_trans('ownership.remove_co_owner_confirm', [
                'person' => User::query()->find($arguments['user'])?->name ?? '',
                'name' => $this->team->name,
            ]))
            ->action(function (array $arguments): void {
                abort_unless($this->canManageOwners(), 403);

                $user = $this->coOwners()->find($arguments['user']);

                if ($user === null) {
                    return;
                }

                $this->team->revokeOwner($user);
                $this->dispatch('team-members-updated');

                Notification::make()
                    ->title(team_trans('ownership.co_owner_removed', ['person' => $user->name]))
                    ->success()
                    ->send();
            });
    }

    public function transferOwnershipAction(): Action
    {
        return AdminAction::make('transferOwnership')
            ->label(team_trans('ownership.transfer'))
            ->icon('heroicon-o-arrow-right-circle')
            ->soft()
            ->color(DaisyColor::WARNING->toFilamentColor())
            ->visible(fn (): bool => $this->canTransferOwnership())
            ->modalHeading(team_trans('ownership.transfer_heading', ['name' => $this->team->name]))
            ->modalDescription(team_trans('ownership.transfer_help', ['role' => Team::defaultOwnerRole()]))
            ->modalWidth(Width::Medium)
            ->schema([
                $this->memberPicker('user_id', team_trans('ownership.transfer_to'), activeOnly: true),
            ])
            ->action(function (array $data): void {
                abort_unless($this->canTransferOwnership(), 403);

                $successor = $this->members(activeOnly: true)->whereKey($data['user_id'])->firstOrFail();

                $this->team->transferOwnership($successor);
                $this->dispatch('team-members-updated');

                Notification::make()
                    ->title(team_trans('ownership.transferred', ['person' => $successor->name]))
                    ->success()
                    ->send();
            });
    }

    public function render(): View
    {
        return view('teams::livewire.team.manage-ownership');
    }

    /**
     * A server-searched member select (a team can have hundreds of members, so
     * never a full option list). The eligible set is re-checked as a rule, so a
     * value that isn't offered isn't accepted either.
     */
    private function memberPicker(string $name, string $label, bool $activeOnly = false, bool $excludeOwners = false): Select
    {
        return Select::make($name)
            ->label($label)
            ->required()
            ->searchable()
            ->getSearchResultsUsing(fn (string $search): array => $this->members($activeOnly, $excludeOwners)
                ->where(function (Builder $query) use ($search): void {
                    $query->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                })
                ->limit(20)
                ->get()
                ->mapWithKeys(fn (User $user): array => [$user->id => "{$user->name} — {$user->email}"])
                ->all())
            ->getOptionLabelUsing(fn (mixed $value): ?string => User::query()->find($value)?->email)
            // Say WHY a choice is refused — a stale search result (someone made a
            // co-owner or suspended since the list was fetched) is the usual way here.
            ->rules([
                fn (): Closure => function (string $attribute, mixed $value, Closure $fail) use ($activeOnly, $excludeOwners): void {
                    $user = $this->team->users()->whereKey($value)->first();

                    // A non-member can't be picked — only a tampered request sends one,
                    // and the action's firstOrFail() answers that with a 404, not a hint.
                    if ($user === null) {
                        return;
                    }

                    if ($this->team->isPrimaryOwner($user)) {
                        $fail(team_trans('ownership.already_primary', ['person' => $user->name]));
                    } elseif ($activeOnly && $user->pivot->suspended_at !== null) {
                        $fail(team_trans('ownership.is_suspended', ['person' => $user->name]));
                    } elseif ($excludeOwners && $user->pivot->is_owner) {
                        $fail(team_trans('ownership.already_co_owner', ['person' => $user->name]));
                    }
                },
            ]);
    }

    /**
     * Members other than the primary owner, optionally only active ones and
     * only non-owners. Stays a relation, never ->getQuery(): the relation's own
     * get()/first() select `users.*` plus aliased pivot columns, whereas the
     * bare builder selects `*` across the join and the pivot row's `id`
     * overwrites the user's — search results then carry the wrong ids.
     */
    private function members(bool $activeOnly = false, bool $excludeOwners = false): BelongsToMany
    {
        $members = $this->team->users()->whereKeyNot($this->team->user_id)->orderBy('users.last_name');

        if ($activeOnly) {
            $members->wherePivotNull('suspended_at');
        }

        if ($excludeOwners) {
            $members->wherePivot('is_owner', false);
        }

        return $members;
    }
}
