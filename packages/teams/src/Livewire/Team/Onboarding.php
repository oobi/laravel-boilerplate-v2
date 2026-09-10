<?php

declare(strict_types=1);

namespace Concise\Teams\Livewire\Team;

use Concise\Teams\Enums\TeamCreationMode;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Shown when a user belongs to no team (personal teams are off by default —
 * OQ1). Rendered in the standard app shell so a user without a team still has a
 * home. The self-service create flow is a setup-time decision still to be
 * designed (scope §16); until then this only explains the situation.
 */
class Onboarding extends Component
{
    public function render(): View
    {
        return view('teams::livewire.team.onboarding', [
            'canCreate' => TeamCreationMode::current()->allowsSelfServiceCreation(),
        ]);
    }
}
