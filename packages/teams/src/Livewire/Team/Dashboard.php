<?php

declare(strict_types=1);

namespace Concise\Teams\Livewire\Team;

use Concise\Teams\Models\Team;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * The landing page for a team, rendered in the team-area shell. The {team} slug
 * is resolved and authorized by the ResolveTeamContext middleware before this runs.
 */
#[Layout('teams::layouts.team')]
class Dashboard extends Component
{
    public Team $team;

    public function mount(Team $team): void
    {
        $this->team = $team;
    }

    public function render(): View
    {
        return view('teams::livewire.team.dashboard', [
            'memberCount' => $this->team->users()->count(),
        ]);
    }
}
