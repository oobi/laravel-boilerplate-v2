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
 * The team area's Domains page — a home for custom-domain management once that
 * tier is on ({@see DomainPolicy::customDomainsEnabled}). It only frames the
 * shared {@see ManageDomains} table (which re-checks access); with the tier off
 * the route 404s and no nav item is offered. Gated by `manageDomains` — a
 * system admin is answered by TeamPolicy::before(); ownership grants no bypass.
 */
#[Layout('teams::layouts.team')]
class Domains extends Component
{
    public Team $team;

    public function mount(Team $team): void
    {
        abort_unless(DomainPolicy::customDomainsEnabled(), 404);

        Gate::authorize(TeamAbility::MANAGE_DOMAINS, $team);

        $this->team = $team;
    }

    /** See ListMembers::booted() — keep the team scope across Livewire updates. */
    public function booted(): void
    {
        app(TeamContext::class)->set($this->team);
    }

    public function render(): View
    {
        return view('teams::livewire.team.domains');
    }
}
