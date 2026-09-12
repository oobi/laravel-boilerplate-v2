<?php

declare(strict_types=1);

namespace Concise\Teams\Livewire\Team;

use App\Enums\SystemPermission;
use App\Models\User;
use App\Support\Filament\AdminAction;
use App\Support\Theme\DaisyColor;
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
use Illuminate\Database\Eloquent\Collection;
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

    /**
     * Promote/demote co-owners in one go: the multi-select is pre-filled with
     * the current co-owners and the diff is applied on save.
     */
    public function coOwnersAction(): Action
    {
        return AdminAction::make('coOwners')
            ->label(team_trans('ownership.manage_co_owners'))
            ->icon('heroicon-o-key')
            ->soft()
            ->visible(fn (): bool => $this->canManageOwners())
            ->modalHeading(team_trans('ownership.manage_co_owners'))
            ->modalDescription(team_trans('ownership.co_owners_help'))
            ->modalWidth(Width::Medium)
            ->fillForm(fn (): array => ['owners' => $this->coOwners()->modelKeys()])
            ->schema([
                Select::make('owners')
                    ->label(team_trans('ownership.co_owners'))
                    ->multiple()
                    ->searchable()
                    ->options(fn (): array => $this->memberOptions()),
            ])
            ->action(function (array $data): void {
                abort_unless($this->canManageOwners(), 403);

                $wanted = collect($data['owners'] ?? [])
                    ->map(fn (mixed $id): int => (int) $id)
                    ->reject(fn (int $id): bool => $id === $this->team->user_id);
                $current = collect($this->coOwners()->modelKeys());

                $this->team->users()->whereKey($wanted->diff($current))->get()
                    ->each(fn (User $user) => $this->team->makeOwner($user));
                $this->team->users()->whereKey($current->diff($wanted))->get()
                    ->each(fn (User $user) => $this->team->revokeOwner($user));

                $this->dispatch('team-members-updated');

                Notification::make()->title(team_trans('ownership.co_owners_updated'))->success()->send();
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
                Select::make('user_id')
                    ->label(team_trans('ownership.transfer_to'))
                    ->required()
                    ->searchable()
                    ->options(fn (): array => $this->memberOptions(activeOnly: true)),
            ])
            ->action(function (array $data): void {
                abort_unless($this->canTransferOwnership(), 403);

                // Must be an active member — the options say so, the server re-checks.
                $successor = $this->team->users()
                    ->wherePivotNull('suspended_at')
                    ->whereKey($data['user_id'])
                    ->firstOrFail();

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
     * Members other than the primary owner, as select options.
     *
     * @return array<int, string>
     */
    private function memberOptions(bool $activeOnly = false): array
    {
        $members = $this->team->users()->whereKeyNot($this->team->user_id);

        if ($activeOnly) {
            $members->wherePivotNull('suspended_at');
        }

        return $members
            ->orderBy('last_name')
            ->get()
            ->mapWithKeys(fn (User $user): array => [$user->id => "{$user->name} — {$user->email}"])
            ->all();
    }
}
