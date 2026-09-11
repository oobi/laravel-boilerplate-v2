<?php

declare(strict_types=1);

namespace Concise\Teams\Livewire\Admin\Teams;

use App\Enums\SystemPermission;
use Concise\Teams\Models\Team;
use Concise\Teams\Support\InvitationPolicy;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * System admin: a team's Invitations tab — the shared pending invitations
 * table, which carries "invite" in its toolbar. Exists only when admin
 * invitations are enabled (with them off the tab is hidden and the route
 * 404s — a backoffice adds members directly instead).
 */
class TeamInvitations extends Component
{
    public Team $team;

    public function mount(Team $team): void
    {
        Gate::authorize(SystemPermission::MANAGE_TEAMS->value);
        abort_unless(InvitationPolicy::adminsMayInvite(), 404);

        $this->team = $team;
    }

    public function render(): View
    {
        return view('teams::livewire.admin.teams.team-invitations');
    }
}
