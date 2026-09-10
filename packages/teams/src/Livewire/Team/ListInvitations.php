<?php

declare(strict_types=1);

namespace Concise\Teams\Livewire\Team;

use Concise\Teams\Enums\TeamAbility;
use Concise\Teams\Enums\TeamCreationMode;
use Concise\Teams\Livewire\Concerns\HasInviteAction;
use Concise\Teams\Models\Team;
use Concise\Teams\Support\TeamContext;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * The team area's Invitations page: invite by email, and the shared pending
 * invitations table (resend / revoke). Exists only when the creation mode
 * lets members invite (admin-provisioned teams have no such page — the nav
 * item isn't registered and the route 404s), and only for the invite
 * permission or an owner.
 */
#[Layout('teams::layouts.team')]
class ListInvitations extends Component implements HasActions, HasSchemas
{
    use HasInviteAction;
    use InteractsWithActions;
    use InteractsWithSchemas;

    public Team $team;

    public function mount(Team $team): void
    {
        $this->team = $team;

        abort_unless(TeamCreationMode::current()->allowsMemberInvitations(), 404);
        Gate::authorize(TeamAbility::INVITE, $team);
    }

    /** See ListMembers::booted(). */
    public function booted(): void
    {
        app(TeamContext::class)->set($this->team);
    }

    public function canInvite(): bool
    {
        return TeamCreationMode::current()->allowsMemberInvitations()
            && Gate::allows(TeamAbility::INVITE, $this->team);
    }

    public function render(): View
    {
        return view('teams::livewire.team.invitations');
    }
}
