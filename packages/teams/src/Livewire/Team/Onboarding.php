<?php

declare(strict_types=1);

namespace Concise\Teams\Livewire\Team;

use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Shown when a user belongs to no team (personal teams are off by default —
 * OQ1). Rendered in the standard app shell so a user without a team still has a
 * home. The create flow itself is wired in 5e per config('teams.creation').
 */
class Onboarding extends Component
{
    public function render(): View
    {
        return view('teams::livewire.team.onboarding', [
            'canCreate' => config('teams.creation') === 'self-service',
        ]);
    }
}
