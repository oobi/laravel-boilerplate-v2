<?php

declare(strict_types=1);

namespace Concise\Teams\Livewire\Team;

use Concise\Teams\Enums\TeamAbility;
use Concise\Teams\Livewire\Concerns\HasCreateTeamAction;
use Concise\Teams\Models\Team;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Shown when a user belongs to no team (personal teams are off by default —
 * OQ1). Rendered in the standard app shell so a user without a team still has
 * a home. Under self-service creation it offers the create modal; otherwise it
 * explains that an admin has to add them.
 */
#[Layout('teams::layouts.lobby')]
class Onboarding extends Component implements HasActions, HasSchemas
{
    use HasCreateTeamAction;
    use InteractsWithActions;
    use InteractsWithSchemas;

    public function render(): View
    {
        return view('teams::livewire.team.onboarding', [
            'canCreate' => Gate::allows(TeamAbility::CREATE, Team::class),
        ]);
    }
}
