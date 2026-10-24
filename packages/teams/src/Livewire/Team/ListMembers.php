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
 * pagination, per-row actions). Opens for anyone who may see the roster
 * (TeamPolicy::viewMembers — the same ability the sidebar link uses, so a link
 * never leads to a 403); the table hides its actions from a view-only holder.
 * Invitations have their own page (ListInvitations).
 */
#[Layout('teams::layouts.team')]
class ListMembers extends Component
{
    public Team $team;

    public function mount(Team $team): void
    {
        $this->team = $team;
    }

    /**
     * Re-establish the team scope on every request, then re-check access.
     * ResolveTeamContext runs on Livewire updates too (persistent middleware)
     * but clears the scope when it finishes, so it is set again here; and it
     * checks membership, not this page's team ability, which is asked here
     * after mount() and on every update, so a role taken away mid-session is
     * refused at the next click.
     */
    public function booted(): void
    {
        app(TeamContext::class)->set($this->team);

        Gate::authorize(TeamAbility::VIEW_MEMBERS, $this->team);
    }

    public function render(): View
    {
        return view('teams::livewire.team.members');
    }
}
