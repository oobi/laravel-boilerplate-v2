<?php

declare(strict_types=1);

namespace App\Providers;

use App\Panels\Users\Examples\PlaceholderTeamMembershipsPanel;
use App\Support\Panels\PanelRegistry;
use Illuminate\Support\ServiceProvider;

/**
 * Demo-only: proves a panel can be added to the User show page without
 * touching ShowUser.php, its view, or config/panels.php. Delete this
 * provider + PlaceholderTeamMembershipsPanel once the real Teams tier
 * (Phase 5 of ~BOILERPLATE_v2.md) ships its own Team Memberships panel.
 */
class PanelExtensionDemoServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        PanelRegistry::extend('users.show', PlaceholderTeamMembershipsPanel::class);
    }
}
