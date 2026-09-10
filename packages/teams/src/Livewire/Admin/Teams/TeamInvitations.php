<?php

declare(strict_types=1);

namespace Concise\Teams\Livewire\Admin\Teams;

use App\Enums\SystemPermission;
use Concise\Teams\Livewire\Concerns\HasInviteAction;
use Concise\Teams\Models\Team;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * System admin: a team's Invitations tab — invite by email (in any creation
 * mode; the mode only restricts members) plus the shared pending invitations
 * table.
 */
class TeamInvitations extends Component implements HasActions, HasSchemas
{
    use HasInviteAction;
    use InteractsWithActions;
    use InteractsWithSchemas;

    public Team $team;

    public function mount(Team $team): void
    {
        Gate::authorize(SystemPermission::MANAGE_TEAMS->value);

        $this->team = $team;
    }

    public function canInvite(): bool
    {
        return Gate::allows(SystemPermission::MANAGE_TEAMS->value);
    }

    public function render(): View
    {
        return view('teams::livewire.admin.teams.team-invitations');
    }
}
