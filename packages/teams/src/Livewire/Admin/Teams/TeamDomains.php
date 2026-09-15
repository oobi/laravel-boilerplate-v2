<?php

declare(strict_types=1);

namespace Concise\Teams\Livewire\Admin\Teams;

use App\Enums\SystemPermission;
use Concise\Teams\Models\Team;
use Concise\Teams\Support\DomainPolicy;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * System admin: a team's Domains tab — the shared {@see ManageDomains} table,
 * its own tab like the team area's. Exists only when the custom-domains tier is
 * on (with it off the tab is hidden and the route 404s). Opens for `view teams`;
 * the child gates management on MANAGE_DOMAINS (a system admin via TeamPolicy::before).
 */
class TeamDomains extends Component
{
    public Team $team;

    public function mount(Team $team): void
    {
        Gate::authorize(SystemPermission::VIEW_TEAMS->value);
        abort_unless(DomainPolicy::customDomainsEnabled(), 404);

        $this->team = $team;
    }

    public function render(): View
    {
        return view('teams::livewire.admin.teams.team-domains');
    }
}
