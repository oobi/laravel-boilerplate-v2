<?php

declare(strict_types=1);

namespace Concise\Teams\Livewire\Team;

use Concise\Teams\Enums\TeamAbility;
use Concise\Teams\Models\Team;
use Concise\Teams\Support\DomainPolicy;
use Concise\Teams\Support\TeamContext;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * The team area's Settings page — a team owner's self-service home. Today it
 * hosts the custom-domains section, so it exists only when the domains overlay
 * gives teams a surface (`teams.domains.team_access` not 'none'); the nav item
 * is registered on the same condition and the route 404s otherwise. Only the
 * team's owner / settings-admin (the `update` ability) may open it.
 */
#[Layout('teams::layouts.team')]
class Settings extends Component
{
    public Team $team;

    public function mount(Team $team): void
    {
        $this->team = $team;

        abort_unless(DomainPolicy::teamCanView(), 404);
        Gate::authorize(TeamAbility::UPDATE, $team);
    }

    /** See ListMembers::booted() — keep the team scope across Livewire updates. */
    public function booted(): void
    {
        app(TeamContext::class)->set($this->team);
    }

    public function render(): View
    {
        return view('teams::livewire.team.settings');
    }
}
