<?php

declare(strict_types=1);

namespace Concise\Teams;

use App\Support\Roles\RoleScopeRegistry;
use Concise\Teams\Enums\TeamAbility;
use Concise\Teams\Models\Team;
use Concise\Teams\Policies\TeamPolicy;
use Concise\Teams\Support\Navigation\TeamNavRegistry;
use Concise\Teams\Support\Roles\TeamRoleScope;
use Concise\Teams\Support\TeamContext;
use Concise\Teams\Support\TeamPermissionResolver;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * The teams tier's single wiring point (see ~dev/TEAMS_TIER_SCOPE.md). Auto-discovered
 * when the package is required. It owns everything teams needs so core stays
 * teams-agnostic: the only core touch-points are the fenced HasTeams seam on
 * User and the two root composer.json entries that install this package.
 */
class TeamsServiceProvider extends ServiceProvider
{
    /**
     * Whether the teams tier is active in this application. Today that means
     * "this provider has booted" (the package is installed and discovered);
     * install/activation tooling (scope §11.5) may refine the signal later, so
     * callers ask here rather than probing config or class existence.
     */
    public static function isActive(): bool
    {
        return app()->providerIsLoaded(static::class);
    }

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

        // The single source of truth for the active team (query/permission/
        // filesystem/cache scope) — see TeamContext and the CurrentTeam facade.
        $this->app->singleton(TeamContext::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'teams');
        $this->loadRoutesFrom(__DIR__.'/../routes/teams.php');

        Gate::policy(Team::class, TeamPolicy::class);

        // Team roles get their own tab on the admin Roles screen.
        RoleScopeRegistry::register(TeamRoleScope::class);

        // The owner is always a member. Ownership itself is structural
        // (teams.user_id) — not a role — and team roles are seeded centrally by
        // TeamRolesSeeder, never per team.
        Team::created(fn (Team $team) => $team->users()->syncWithoutDetaching([$team->user_id]));

        $this->registerTeamNavigation();
    }

    /**
     * The team area's default sidebar sections. Add-ons extend it via
     * TeamNavRegistry the same way they extend the system nav; abilities resolve
     * against the current team (see the team-sidebar-nav partial). Dashboard is
     * rendered directly by the partial, so it isn't registered here.
     */
    private function registerTeamNavigation(): void
    {
        TeamNavRegistry::item('team-members')
            ->label(__('Members'))
            ->route('team.members')
            ->icon('heroicon-o-users')
            ->active('team.members')
            ->can(TeamAbility::MANAGE_MEMBERS)
            ->order(10);
    }
}
