<?php

declare(strict_types=1);

namespace Concise\Teams;

use Concise\Teams\Support\TeamPermissionResolver;
use Illuminate\Support\ServiceProvider;

/**
 * The teams tier's single wiring point (see ~dev/TEAMS_TIER_SCOPE.md). Auto-discovered
 * when the package is required. It owns everything teams needs so core stays
 * teams-agnostic: the only core touch-points are the fenced HasTeams seam on
 * User and the two root composer.json entries that install this package.
 */
class TeamsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/teams.php', 'teams');

        // Enable spatie/laravel-permission's teams feature so roles/permissions
        // resolve per team, and default the "no team" scope to the reserved
        // system id (0) so existing system roles keep working (see
        // TeamPermissionResolver). Set in register() so both are in place before
        // the permission migrations read them and before PermissionRegistrar
        // boots. This is why teams never edits config/permission.php.
        config([
            'permission.teams' => true,
            'permission.team_resolver' => TeamPermissionResolver::class,
        ]);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
