<?php

declare(strict_types=1);

namespace App\Panels\Users\Examples;

use App\Support\Panels\Concerns\HasPanelMetadata;
use App\Support\Panels\Contracts\PanelRegion;
use App\Support\Panels\Contracts\ShowPanel;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;

/**
 * Demo-only panel proving an add-on can contribute a card to the User show
 * page without touching ShowUser.php, its view, or App\Support\Panels\AdminPanels — see
 * PanelExtensionDemoServiceProvider. Delete both once the real Teams tier
 * (Phase 5 of ~BOILERPLATE_v2.md) ships its own Team Memberships panel.
 */
class PlaceholderTeamMembershipsPanel implements ShowPanel
{
    use HasPanelMetadata;

    public function order(): int
    {
        return 5;
    }

    public function region(): PanelRegion
    {
        return PanelRegion::Main;
    }

    public function render(Model $subject): View
    {
        return view('panels.users.examples.placeholder-team-memberships');
    }
}
