<?php

declare(strict_types=1);

namespace Concise\Teams\Livewire\Team;

use Concise\Teams\Enums\TeamAbility;
use Concise\Teams\Models\Team;
use Concise\Teams\Support\TeamContext;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * The team area's Members page: the shared members table (search, filters,
 * pagination, per-row actions). Reached only by members holding the
 * manage-members permission or an owner (TeamPolicy::manageMembers).
 * Invitations have their own page (ListInvitations).
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
     * so anything team-scoped here (disk, cache) would otherwise fail loud.
     */
    public function booted(): void
    {
        app(TeamContext::class)->set($this->team);
    }

    public function render(): View
    {
        return view('teams::livewire.team.members');
    }
}
