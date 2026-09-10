<?php

declare(strict_types=1);

namespace Concise\Teams\Livewire\Admin\Teams;

use App\Enums\SystemPermission;
use App\Models\User;
use Concise\Teams\Models\Team;
use Concise\Teams\Support\TeamRoleField;
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
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * System admin: a team's Members tab — the shared members table plus "add
 * member" (an existing account, with a role). A system admin manages members
 * without being one, and never enters the team's context.
 */
class TeamMembers extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    public Team $team;

    public function mount(Team $team): void
    {
        Gate::authorize(SystemPermission::MANAGE_TEAMS->value);

        $this->team = $team;
    }

    public function addMemberAction(): Action
    {
        return Action::make('addMember')
            ->label(__('Add member'))
            ->icon('heroicon-o-user-plus')
            ->modalHeading(__('Add a member to :team', ['team' => $this->team->name]))
            ->modalWidth(Width::Medium)
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
                $this->dispatch('team-members-updated');

                Notification::make()
                    ->title(__(':name added to :team', ['name' => $user->name, 'team' => $this->team->name]))
                    ->success()
                    ->send();
            });
    }

    public function render(): View
    {
        return view('teams::livewire.admin.teams.team-members');
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
}
