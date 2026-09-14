<?php

declare(strict_types=1);

namespace App\Enums;

use App\Support\Roles\Implications;

/**
 * The fixed, code-checked vocabulary of application-wide capabilities. Each
 * case corresponds to a real Gate/Policy check somewhere in the app and is
 * seeded as a spatie/laravel-permission Permission row (see PermissionSeeder)
 * — role -> permission *assignment* is admin-configurable via the Roles
 * screen, never a hardcoded match(). See .ai/rules/providers.md.
 *
 * Shape: `access admin panel` is entry only — the shell, the dashboard, the
 * navigation. Each admin area has its own read floor (`view users`, an add-on's
 * `view teams`, `view system analytics`) so a role can reach the panel for one
 * module without browsing another's data; the action permissions of an area
 * sit on top of its read floor. That shape is enforced by implies(): every
 * action carries its area's view, every view carries panel entry, applied when
 * a role is WRITTEN (withImplied()) so a stored grant is always reachable and
 * `checkPermissionTo()` and the Gate agree — see App\Support\Roles\Implications
 * and the `bp:roles:sync-implications` data fix for a map that changes later.
 */
enum SystemPermission: string
{
    case ACCESS_ADMIN_PANEL = 'access admin panel';
    case MANAGE_SYSTEM_SETTINGS = 'manage system settings';
    case VIEW_SYSTEM_ANALYTICS = 'view system analytics';
    case VIEW_USERS = 'view users';
    case MANAGE_USERS = 'manage users';
    case SUSPEND_USERS = 'suspend users';
    case DELETE_USERS = 'delete users';
    case IMPERSONATE_USERS = 'impersonate users';
    // teams:start — contributed by the teams tier (packages/teams): the system-level "manage all teams" area. Removed on uninstall.
    // `view teams` is the read floor (admin entry to the teams area, read-only); `manage teams` the
    // day-to-day edits (create, settings, members, roles, invitations, domains); the destructive and
    // ownership acts have their own finer permissions, mirroring the user set.
    case VIEW_TEAMS = 'view teams';
    case MANAGE_TEAMS = 'manage teams';
    case DEACTIVATE_TEAMS = 'deactivate teams';
    case DELETE_TEAMS = 'delete teams';
    case MANAGE_TEAM_OWNERSHIP = 'manage team ownership';
    // teams:end

    public function label(): string
    {
        return match ($this) {
            self::ACCESS_ADMIN_PANEL => __('permissions.labels.access_admin_panel'),
            self::MANAGE_SYSTEM_SETTINGS => __('permissions.labels.manage_system_settings'),
            self::VIEW_SYSTEM_ANALYTICS => __('permissions.labels.view_system_analytics'),
            self::VIEW_USERS => __('permissions.labels.view_users'),
            self::MANAGE_USERS => __('permissions.labels.manage_users'),
            self::SUSPEND_USERS => __('permissions.labels.suspend_users'),
            self::DELETE_USERS => __('permissions.labels.delete_users'),
            self::IMPERSONATE_USERS => __('permissions.labels.impersonate_users'),
            // teams:start — labels relabel with the tier (values above stay fixed identifiers).
            self::VIEW_TEAMS => team_trans('permissions.system_view_teams'),
            self::MANAGE_TEAMS => team_trans('permissions.system_manage_teams'),
            self::DEACTIVATE_TEAMS => team_trans('permissions.system_deactivate_teams'),
            self::DELETE_TEAMS => team_trans('permissions.system_delete_teams'),
            self::MANAGE_TEAM_OWNERSHIP => team_trans('permissions.system_manage_team_ownership'),
            // teams:end
        };
    }

    public function category(): string
    {
        return match ($this) {
            self::VIEW_USERS,
            self::MANAGE_USERS,
            self::SUSPEND_USERS,
            self::DELETE_USERS,
            self::IMPERSONATE_USERS => __('permissions.categories.user_management'),

            self::MANAGE_SYSTEM_SETTINGS,
            self::VIEW_SYSTEM_ANALYTICS,
            self::ACCESS_ADMIN_PANEL => __('permissions.categories.system_administration'),

            // teams:start — the teams tier's own group on the Roles screen (relabels with the tier).
            self::VIEW_TEAMS,
            self::MANAGE_TEAMS,
            self::DEACTIVATE_TEAMS,
            self::DELETE_TEAMS,
            self::MANAGE_TEAM_OWNERSHIP => team_trans('permissions.category_teams'),
            // teams:end
        };
    }

    /**
     * What this permission carries with it, one hop: an action carries its
     * area's read floor, a read floor carries panel entry. Declared here once;
     * applied transitively by withImplied() at every write (the seeder, the
     * Roles form) and shown by the form ("Included with …"). Why: a grant that
     * can't be reached — `delete users` on a role that can't open the users
     * area — looks like a working role and isn't.
     *
     * @return list<self>
     */
    public function implies(): array
    {
        return match ($this) {
            self::VIEW_USERS,
            self::MANAGE_SYSTEM_SETTINGS,
            self::VIEW_SYSTEM_ANALYTICS => [self::ACCESS_ADMIN_PANEL],

            self::MANAGE_USERS,
            self::SUSPEND_USERS,
            self::DELETE_USERS,
            self::IMPERSONATE_USERS => [self::VIEW_USERS],

            // teams:start
            self::VIEW_TEAMS => [self::ACCESS_ADMIN_PANEL],
            self::MANAGE_TEAMS,
            self::DEACTIVATE_TEAMS,
            self::DELETE_TEAMS,
            self::MANAGE_TEAM_OWNERSHIP => [self::VIEW_TEAMS],
            // teams:end

            default => [],
        };
    }

    /**
     * A grant closed over its implications, transitively — what every write
     * path stores. Order preserved, no duplicates.
     *
     * @param  list<self>  $permissions
     * @return list<self>
     */
    public static function withImplied(array $permissions): array
    {
        return collect(Implications::close(
            self::implicationMap(),
            array_map(fn (self $permission): string => $permission->value, $permissions),
        ))->map(fn (string $name): self => self::from($name))->all();
    }

    /**
     * implies() as the Roles form and the sync command consume it: permission
     * name => the names it carries (one hop; consumers close the chain).
     *
     * @return array<string, list<string>>
     */
    public static function implicationMap(): array
    {
        return collect(self::cases())
            ->filter(fn (self $case): bool => $case->implies() !== [])
            ->mapWithKeys(fn (self $case): array => [
                $case->value => array_map(fn (self $implied): string => $implied->value, $case->implies()),
            ])
            ->all();
    }

    /** @return array<string, list<SystemPermission>> */
    public static function byCategory(): array
    {
        $categories = [];

        foreach (self::cases() as $permission) {
            $categories[$permission->category()][] = $permission;
        }

        return $categories;
    }
}
