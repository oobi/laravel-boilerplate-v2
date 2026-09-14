<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The fixed, code-checked vocabulary of application-wide capabilities. Each
 * case corresponds to a real Gate/Policy check somewhere in the app and is
 * seeded as a spatie/laravel-permission Permission row (see PermissionSeeder)
 * — role -> permission *assignment* is admin-configurable via the Roles
 * screen, never a hardcoded match(). See .ai/rules/providers.md.
 */
enum SystemPermission: string
{
    case ACCESS_ADMIN_PANEL = 'access admin panel';
    case MANAGE_SYSTEM_SETTINGS = 'manage system settings';
    case VIEW_SYSTEM_ANALYTICS = 'view system analytics';
    case MANAGE_USERS = 'manage users';
    case SUSPEND_USERS = 'suspend users';
    case DELETE_USERS = 'delete users';
    case IMPERSONATE_USERS = 'impersonate users';
    // teams:start — contributed by the teams tier (packages/teams): the system-level "manage all teams" area. Removed on uninstall.
    // `manage teams` is the floor (admin entry, create, settings, members, roles, invitations, domains);
    // the destructive/ownership acts split off into their own finer permissions, mirroring the user set.
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
            self::MANAGE_USERS => __('permissions.labels.manage_users'),
            self::SUSPEND_USERS => __('permissions.labels.suspend_users'),
            self::DELETE_USERS => __('permissions.labels.delete_users'),
            self::IMPERSONATE_USERS => __('permissions.labels.impersonate_users'),
            // teams:start — labels relabel with the tier (values above stay fixed identifiers).
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
            self::MANAGE_USERS,
            self::SUSPEND_USERS,
            self::DELETE_USERS,
            self::IMPERSONATE_USERS => __('permissions.categories.user_management'),

            self::MANAGE_SYSTEM_SETTINGS,
            self::VIEW_SYSTEM_ANALYTICS,
            self::ACCESS_ADMIN_PANEL => __('permissions.categories.system_administration'),

            // teams:start — the teams tier's own group on the Roles screen (relabels with the tier).
            self::MANAGE_TEAMS,
            self::DEACTIVATE_TEAMS,
            self::DELETE_TEAMS,
            self::MANAGE_TEAM_OWNERSHIP => team_trans('permissions.category_teams'),
            // teams:end
        };
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
