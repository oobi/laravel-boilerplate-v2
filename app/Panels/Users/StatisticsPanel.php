<?php

declare(strict_types=1);

namespace App\Panels\Users;

use App\Support\Panels\Concerns\HasPanelMetadata;
use App\Support\Panels\PanelRegion;
use App\Support\Panels\ShowPanel;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;

/** Account age / last login, in the sidebar of the User show page. */
class StatisticsPanel implements ShowPanel
{
    use HasPanelMetadata;

    public function order(): int
    {
        return 10;
    }

    public function region(): PanelRegion
    {
        return PanelRegion::Sidebar;
    }

    public function render(Model $subject): View
    {
        return view('panels.users.statistics', ['user' => $subject]);
    }
}
