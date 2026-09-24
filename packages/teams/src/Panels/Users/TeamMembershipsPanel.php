<?php

declare(strict_types=1);

namespace Concise\Teams\Panels\Users;

use App\Support\Panels\Concerns\HasPanelMetadata;
use App\Support\Panels\Contracts\ShowPanel;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;

/**
 * The teams a user belongs to, on the admin User show page. Registered from
 * TeamsServiceProvider via PanelRegistry — the tier's card on a core page
 * without touching ShowUser or AdminPanels. The paginated list itself is the
 * UserMemberships Livewire component.
 */
class TeamMembershipsPanel implements ShowPanel
{
    use HasPanelMetadata;

    public function order(): int
    {
        return 5;
    }

    public function render(Model $subject): View
    {
        return view('teams::panels.users.team-memberships', ['user' => $subject]);
    }
}
