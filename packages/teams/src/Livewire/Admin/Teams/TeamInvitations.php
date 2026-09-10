<?php

declare(strict_types=1);

namespace Concise\Teams\Livewire\Admin\Teams;

use App\Enums\SystemPermission;
use Concise\Teams\Models\Team;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * System admin: a team's Invitations tab — the shared pending invitations
 * table, which carries "invite" in its toolbar (in any creation mode; the
 * mode only restricts members).
 */
class TeamInvitations extends Component
{
    public Team $team;

    public function mount(Team $team): void
    {
        Gate::authorize(SystemPermission::MANAGE_TEAMS->value);

        $this->team = $team;
    }

    public function render(): View
    {
        return view('teams::livewire.admin.teams.team-invitations');
    }
}
