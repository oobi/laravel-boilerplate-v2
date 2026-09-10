<?php

declare(strict_types=1);

namespace Concise\Teams\Livewire\Team;

use App\Models\Role;
use App\Models\User;
use Concise\Teams\Enums\TeamAbility;
use Concise\Teams\Models\Team;
use Concise\Teams\Support\TeamContext;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Team members list with per-member role changes and removal. Reached only by
 * members holding the manage-members permission or the owner
 * (TeamPolicy::manageMembers). The owner can't be changed or removed here.
 */
#[Layout('teams::layouts.team')]
class ListMembers extends Component
{
    public Team $team;

    public function mount(Team $team): void
    {
        $this->team = $team;
        Gate::authorize(TeamAbility::MANAGE_MEMBERS, $team);
    }

    /**
     * Re-establish the team scope on every request. The route middleware sets it
     * for the initial render, but Livewire's update requests bypass that route —
     * so role reads/writes here would otherwise resolve in the system scope.
     */
    public function booted(): void
    {
        app(TeamContext::class)->set($this->team);
    }

    public function changeRole(int $userId, string $role): void
    {
        Gate::authorize(TeamAbility::MANAGE_MEMBERS, $this->team);

        if ($userId === $this->team->user_id || ! $this->teamRoles()->contains('name', $role)) {
            return;
        }

        $this->team->users()->findOrFail($userId)->syncRoles([$role]);
    }

    public function removeMember(int $userId): void
    {
        Gate::authorize(TeamAbility::MANAGE_MEMBERS, $this->team);

        if ($userId === $this->team->user_id) {
            return; // the owner is never removed from their own team
        }

        User::find($userId)?->syncRoles([]);
        $this->team->users()->detach($userId);
    }

    public function render(): View
    {
        // Roles eager-load in the team scope (set in booted()).
        $members = $this->team->users()->with('roles')->orderBy('first_name')->orderBy('last_name')->get();

        return view('teams::livewire.team.members', [
            'members' => $members,
            'roles' => $this->teamRoles(),
        ]);
    }

    /** The centrally-defined roles a member may hold. @return \Illuminate\Database\Eloquent\Collection<int, Role> */
    private function teamRoles(): Collection
    {
        return Team::availableRoles()->orderBy('name')->get();
    }
}
