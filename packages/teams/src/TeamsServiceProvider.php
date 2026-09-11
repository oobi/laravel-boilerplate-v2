<?php

declare(strict_types=1);

namespace Concise\Teams;

use App\Enums\SystemPermission;
use App\Models\User;
use App\Support\AccountMenu\AccountMenuRegistry;
use App\Support\Auth\LoginRedirectRegistry;
use App\Support\Breadcrumbs;
use App\Support\Navigation\Registry\NavItem;
use App\Support\Navigation\Registry\NavRegistry;
use App\Support\Panels\Registry\PanelRegistry;
use App\Support\Roles\RoleScopeRegistry;
use Concise\Teams\Enums\TeamAbility;
use Concise\Teams\Livewire\Team\MembersTable;
use Concise\Teams\Livewire\Team\PendingInvitations;
use Concise\Teams\Models\Team;
use Concise\Teams\Panels\Users\TeamMembershipsPanel;
use Concise\Teams\Policies\TeamPolicy;
use Concise\Teams\Support\Dns\DnsResolver;
use Concise\Teams\Support\Dns\SystemDnsResolver;
use Concise\Teams\Support\InvitationPolicy;
use Concise\Teams\Support\Navigation\TeamNavRegistry;
use Concise\Teams\Support\Roles\TeamRoleScope;
use Concise\Teams\Support\TeamContext;
use Concise\Teams\Support\TeamLabels;
use Concise\Teams\Support\TeamPermissionResolver;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

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

        // The DNS boundary for custom-domain verification (5h). Swapped for a
        // FakeDnsResolver in tests.
        $this->app->bind(DnsResolver::class, SystemDnsResolver::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'teams');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'teams');
        $this->loadRoutesFrom(__DIR__.'/../routes/teams.php');

        // `php artisan vendor:publish --tag=teams-config` / `--tag=teams-lang` to override in the app.
        $this->publishes([__DIR__.'/../config/teams.php' => config_path('teams.php')], 'teams-config');
        $this->publishes([__DIR__.'/../lang' => lang_path('vendor/teams')], 'teams-lang');

        // The admin breadcrumb for `teams.*` routes uses the project's word for teams (scope §8).
        Breadcrumbs::label('teams', fn (): string => TeamLabels::plural());

        // Child components shared by the team area and the system admin pages.
        Livewire::component('teams-members-table', MembersTable::class);
        Livewire::component('teams-pending-invitations', PendingInvitations::class);

        Gate::policy(Team::class, TeamPolicy::class);

        // Team roles get their own tab on the admin Roles screen.
        RoleScopeRegistry::register(TeamRoleScope::class);

        $this->registerSystemAdminArea();
        $this->registerAccountMenu();
        $this->registerLoginRedirect();

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
            ->label(team_trans('nav.members'))
            ->route('team.members')
            ->icon('heroicon-o-users')
            ->active('team.members')
            ->can(TeamAbility::MANAGE_MEMBERS)
            ->order(10);

        // With member invitations off there's no page and no nav item (see InvitationPolicy).
        if (InvitationPolicy::membersMayInvite()) {
            TeamNavRegistry::item('team-invitations')
                ->label(team_trans('nav.invitations'))
                ->route('team.invitations')
                ->icon('heroicon-o-envelope')
                ->active('team.invitations')
                ->can(TeamAbility::INVITE)
                ->order(20);
        }
    }

    /**
     * The system admin side (scope §7 "manage all teams"): a Teams entry in the
     * admin sidebar's Management group and the memberships panel on the user
     * Show page — both contributed through core's registries, so no core nav
     * or panel file changes.
     */
    /**
     * Cross-area links in the header account menu (avatar dropdown). Both exist
     * only because the tier adds a second area beside the system admin: "Teams"
     * to reach it, "Admin dashboard" to get back. They're always shown where the
     * viewer has the capability (a dropdown that's occasionally in-context is
     * cheaper than fragile route detection); uninstalling the tier removes both.
     */
    private function registerAccountMenu(): void
    {
        AccountMenuRegistry::item('teams')
            ->label(fn (): string => team_trans('nav.my_teams'))
            ->url(fn (): string => route('team.index'))
            ->icon('heroicon-o-user-group')
            ->order(10)
            ->visibleWhen(fn (?User $user): bool => $user !== null
                && ($user->accessibleTeams()->exists() || Gate::forUser($user)->allows(TeamAbility::CREATE, Team::class)));

        AccountMenuRegistry::item('admin-dashboard')
            ->label(fn (): string => team_trans('nav.admin_dashboard'))
            ->url(fn (): string => route('dashboard'))
            ->icon('heroicon-o-squares-2x2')
            ->order(20)
            ->visibleWhen(fn (?User $user): bool => $user !== null
                && Gate::forUser($user)->allows(SystemPermission::ACCESS_ADMIN_PANEL->value));
    }

    /**
     * After login, a user without a system role is sent to the team area — their
     * team, the picker, or the "ask an admin" onboarding, as TeamRedirect decides.
     * Contributed to core's LoginRedirectRegistry so core auth stays teams-agnostic;
     * a system role is resolved by core before this resolver is ever consulted.
     */
    private function registerLoginRedirect(): void
    {
        LoginRedirectRegistry::register(
            fn (User $user): ?string => $user->canAccessAdmin() ? null : route('team.index'),
        );
    }

    private function registerSystemAdminArea(): void
    {
        NavRegistry::group('management')->add(
            NavItem::make('teams')
                ->label(TeamLabels::plural())
                ->route('teams.index')
                ->icon('heroicon-o-user-group')
                ->active('teams.*')
                ->can(SystemPermission::MANAGE_TEAMS)
                ->order(30),
        );

        PanelRegistry::for('users.show')->add(TeamMembershipsPanel::class);
    }
}
