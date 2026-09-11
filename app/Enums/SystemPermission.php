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
    case MANAGE_TEAMS = 'manage teams';
    // teams:end

    public function label(): string
    {
        return match ($this) {
            self::ACCESS_ADMIN_PANEL => 'Access Admin Panel',
            self::MANAGE_SYSTEM_SETTINGS => 'Manage System Settings',
            self::VIEW_SYSTEM_ANALYTICS => 'View System Analytics',
            self::MANAGE_USERS => 'Manage Users',
            self::SUSPEND_USERS => 'Suspend Users',
            self::DELETE_USERS => 'Delete Users',
            self::IMPERSONATE_USERS => 'Impersonate Users',
            // teams:start
            self::MANAGE_TEAMS => 'Manage Teams',
            // teams:end
        };
    }

    public function category(): string
    {
        return match ($this) {
            self::MANAGE_USERS,
            self::SUSPEND_USERS,
            self::DELETE_USERS,
            self::IMPERSONATE_USERS => 'User Management',

            self::MANAGE_SYSTEM_SETTINGS,
            self::VIEW_SYSTEM_ANALYTICS,
            self::ACCESS_ADMIN_PANEL => 'System Administration',

            // teams:start
            self::MANAGE_TEAMS => 'System Administration',
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
