<?php

declare(strict_types=1);

namespace Concise\Teams;

use App\Enums\SystemPermission;
use App\Models\User;
use App\Support\AccountArea\AccountAreaRegistry;
use App\Support\AccountArea\AccountShell;
use App\Support\AccountMenu\AccountMenuRegistry;
use App\Support\Auth\LoginRedirectRegistry;
use App\Support\Breadcrumbs;
use App\Support\Navigation\Registry\NavItem;
use App\Support\Navigation\Registry\NavRegistry;
use App\Support\Panels\Registry\PanelRegistry;
use App\Support\Roles\RoleScopeRegistry;
use App\Support\Roles\SystemPermissionRegistry;
use App\Support\TrustedHosts;
use App\Support\UserDeletionGuards;
use App\Support\UserListColumns;
use Concise\Teams\Enums\TeamAbility;
use Concise\Teams\Enums\TeamSystemPermission;
use Concise\Teams\Http\Middleware\ResolveTeamContext;
use Concise\Teams\Livewire\Admin\UserMemberships;
use Concise\Teams\Livewire\Team\ManageDomains;
use Concise\Teams\Livewire\Team\ManageOwnership;
use Concise\Teams\Livewire\Team\MembersTable;
use Concise\Teams\Livewire\Team\PendingInvitations;
use Concise\Teams\Models\Domain;
use Concise\Teams\Models\Team;
use Concise\Teams\Models\TeamInvitation;
use Concise\Teams\Panels\Users\TeamMembershipsPanel;
use Concise\Teams\Policies\TeamPolicy;
use Concise\Teams\Support\Dns\DnsResolver;
use Concise\Teams\Support\Dns\SystemDnsResolver;
use Concise\Teams\Support\DomainPolicy;
use Concise\Teams\Support\Navigation\TeamNavRegistry;
use Concise\Teams\Support\Roles\TeamRoleScope;
use Concise\Teams\Support\TeamContext;
use Concise\Teams\Support\TeamDestination;
use Concise\Teams\Support\TeamLabels;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\ViewColumn;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

/**
 * The teams tier's single wiring point. Auto-discovered when the package is
 * required. It owns everything teams needs so core stays teams-agnostic: the
 * only core touch-points are the fenced HasTeams seam on User and the two root
 * composer.json entries that install this package (see docs/boilerplate/teams-distribution.md).
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

        // The single source of truth for the active team (query/filesystem/cache
        // scope — not permissions) — see TeamContext and the CurrentTeam facade.
        $this->app->singleton(TeamContext::class);

        // The DNS boundary for custom-domain verification (5h). Swapped for a
        // FakeDnsResolver in tests.
        $this->app->bind(DnsResolver::class, SystemDnsResolver::class);
    }

    public function boot(): void
    {
        // Host mode needs admin_host + base; fail loud before routes register (5h.4).
        DomainPolicy::assertConfigured();

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
        Livewire::component('teams-manage-domains', ManageDomains::class);
        Livewire::component('teams-manage-ownership', ManageOwnership::class);
        Livewire::component('teams-user-memberships', UserMemberships::class);

        // Livewire re-runs only its persistent middleware on an update request.
        // Membership and the team's active flag are checked here, so without this
        // someone removed from a team keeps reading its pages until they reload.
        Livewire::addPersistentMiddleware(ResolveTeamContext::class);

        Gate::policy(Team::class, TeamPolicy::class);

        // Team roles get their own tab on the admin Roles screen; the system-level
        // "manage all teams" permissions join core's on the system tab.
        RoleScopeRegistry::register(TeamRoleScope::class);
        SystemPermissionRegistry::register(TeamSystemPermission::class);

        $this->registerSystemAdminArea();
        $this->registerAccountMenu();
        $this->registerLoginRedirect();
        $this->registerAccountArea();
        $this->registerTrustedHosts();
        $this->registerUserDeletionGuard();

        // The owner is always a member. Ownership itself is structural
        // (teams.user_id) — a shield, not a permission bypass — so the owner's
        // authority comes from a role: give them the configured default at setup.
        // Lenient here (factories, seeders, imports); the user-facing creation
        // paths refuse loudly when the role is missing (DefaultOwnerRoleMissing).
        // Team roles are still seeded centrally by TeamRolesSeeder, never per team.
        Team::created(function (Team $team): void {
            $team->users()->syncWithoutDetaching([$team->user_id]);
            $team->ensureHoldsDefaultOwnerRole($team->owner);
        });

        $this->registerTeamNavigation();

        // Expired invitations are listed as expired for a while, then removed.
        // model:prune only finds app/Models by itself, so name the model.
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command('model:prune', ['--model' => [TeamInvitation::class]])->daily();
        });
    }

    /**
     * The team area's default sidebar sections. Mirrors the system admin shell:
     * Dashboard stands alone (rendered directly by the partial), and everything
     * else lives under one collapsible "Management" group, so the sidebar stays
     * two curated sections instead of a flat list. The two secondary surfaces —
     * Invitations (beside Members) and Domains (beside Settings) — are tabs on
     * their sibling page rather than their own nav items, so the group holds just
     * Members and Settings; each item stays active on its sibling tab's route.
     * Add-ons extend the group via TeamNavRegistry the same way they extend the
     * system nav; abilities resolve against the current team.
     */
    private function registerTeamNavigation(): void
    {
        TeamNavRegistry::group('management')
            ->label(__('Management'))
            ->order(10)
            ->add(
                // The tier's :Members label (e.g. "Staff"). Invitations is a tab
                // on this page now, so the item stays active there too.
                NavItem::make('team-members')
                    ->label(team_trans('nav.members'))
                    ->route('team.members')
                    ->icon('heroicon-o-users')
                    ->active('team.members', 'team.invitations')
                    ->can(TeamAbility::VIEW_MEMBERS)
                    ->order(10),

                // Team details + ownership. Shown to whoever can view it — owners
                // (read-only floor) or holders of update/manageDomains. Custom
                // domains, when that tier is on, live as a tab here, so the item
                // stays active on the domains route too.
                NavItem::make('team-settings')
                    ->label(team_trans('nav.settings'))
                    ->route('team.settings')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->active('team.settings', 'team.domains')
                    ->can(TeamAbility::VIEW_SETTINGS)
                    ->order(30),
            );
    }

    /**
     * The system admin side (scope §7 "manage all teams"): a Teams entry in the
     * admin sidebar's Business group and the memberships panel on the user
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
            // The picker, not the front door (team.index): the front door sends a
            // system user to the dashboard, but "My teams" must let anyone — admins
            // included — choose a team to enter.
            ->url(fn (): string => route('team.select'))
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
     * After login, a user without a system role is sent straight to where they
     * belong — their team, the picker, or the "ask an admin" onboarding, via
     * TeamDestination (the same resolver the `/{prefix}` front door uses) —
     * rather than through the front door itself, so login is one hop, not two.
     * Contributed to core's LoginRedirectRegistry so core auth stays
     * teams-agnostic; a system role is resolved by core before this resolver is
     * ever consulted.
     */
    private function registerLoginRedirect(): void
    {
        LoginRedirectRegistry::register(
            fn (User $user): ?string => $user->canAccessAdmin() ? null : TeamDestination::resolve($user),
        );
    }

    /**
     * In host mode the app also answers on `base` and every `{slug}.base`, the
     * admin and account hosts, and (custom-domains tier on) each verified
     * custom domain. Read per request, so config and new domains apply at once.
     */
    private function registerTrustedHosts(): void
    {
        TrustedHosts::register(static function (): array {
            $base = DomainPolicy::base();

            // assertConfigured() requires base in host mode; an empty one would trust any host ending in a dot.
            if (! DomainPolicy::enabled() || $base === null) {
                return [];
            }

            $hosts = array_map(TrustedHosts::exact(...), array_filter([DomainPolicy::adminHost(), DomainPolicy::accountHost()]));
            $hosts[] = TrustedHosts::withSubdomains($base);

            if (DomainPolicy::customDomainsEnabled()) {
                array_push($hosts, ...array_map(TrustedHosts::exact(...), Domain::verifiedHosts()));
            }

            return $hosts;
        });

        // A permanently deleted team's domains go by database cascade, without Domain's own events.
        Team::forceDeleted(fn (): bool => Cache::forget(Domain::VERIFIED_HOSTS_CACHE_KEY));
    }

    /**
     * A team's primary owner can't be deleted while they own it (GitHub
     * oobi#28): transfer the team or delete it first. Deleted teams count only
     * for a permanent delete, which the `teams.user_id` foreign key would
     * otherwise refuse.
     */
    private function registerUserDeletionGuard(): void
    {
        UserDeletionGuards::register(static function (User $user, bool $permanently): ?string {
            [$deleted, $live] = Team::query()
                ->when($permanently, fn ($query) => $query->withTrashed())
                ->where('user_id', $user->getKey())
                ->orderBy('name')
                ->get(['name', 'deleted_at'])
                ->partition(fn (Team $team): bool => $team->trashed());

            // Live teams can be transferred; deleted ones only purged.
            $key = $live->isNotEmpty() ? 'ownership.owner_cannot_be_deleted' : 'ownership.owner_of_deleted_teams';
            $names = $live->isNotEmpty() ? $live : $deleted;

            return $names->isEmpty() ? null : team_trans_choice($key, $names->count(), [
                'person' => $user->name,
                'names' => $names->pluck('name')->join(', ', ' '.team_trans('ownership.and').' '),
            ]);
        });
    }

    /**
     * The profile opened from a team (remembered by ResolveTeamContext) keeps that
     * team's shell, re-checking the same gates as entering it: the team still
     * exists, is active, and the user is a member. The team is handed to the
     * layout, not set as the TeamContext, so profile requests stay unscoped.
     */
    private function registerAccountArea(): void
    {
        AccountAreaRegistry::register('team', function (User $user, ?string $teamId): ?AccountShell {
            $team = $teamId === null ? null : Team::find($teamId);

            return $team instanceof Team && $team->active && $user->belongsToTeam($team)
                ? new AccountShell('teams::layouts.team', ['team' => $team])
                : null;
        });
    }

    private function registerSystemAdminArea(): void
    {
        NavRegistry::group('business')->add(
            NavItem::make('teams')
                ->label(TeamLabels::plural())
                ->route('teams.index')
                ->icon('heroicon-o-user-group')
                ->active('teams.*')
                ->can(TeamSystemPermission::VIEW_TEAMS)
                ->order(30),
        );

        PanelRegistry::for('users.show')->add(TeamMembershipsPanel::class);

        $this->registerUsersListColumn();
    }

    /**
     * A Teams column on the admin Users list: the count (suspended memberships
     * included) and, for a single team, its slug and name, all as subqueries so
     * the list stays one query. Deleted teams don't count.
     */
    private function registerUsersListColumn(): void
    {
        $teamsOfUser = fn (string $column): Builder => Team::query()
            ->select("teams.{$column}")
            ->join('team_user', 'team_user.team_id', '=', 'teams.id')
            ->whereColumn('team_user.user_id', 'users.id')
            // Read only when there's one live team; the order just keeps the pick deterministic.
            ->orderBy('team_user.id')
            ->limit(1);

        UserListColumns::register(
            'teams',
            fn (): Column => ViewColumn::make('teams')
                ->label(team_trans('memberships.column'))
                ->view('teams::filament.tables.columns.user-teams-column')
                ->viewData(['linked' => Gate::allows(TeamSystemPermission::VIEW_TEAMS->value)])
                ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('teams_count', $direction)),
            fn (Builder $query): Builder => $query
                ->withCount('teams')
                ->addSelect(['only_team_slug' => $teamsOfUser('slug'), 'only_team_name' => $teamsOfUser('name')]),
        );
    }
}
