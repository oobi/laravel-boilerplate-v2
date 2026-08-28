<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Application-wide (non-team) permissions, granted via a SystemRole.
 */
enum SystemPermission: string
{
    case MANAGE_USERS = 'manage users';
    case MANAGE_SYSTEM_SETTINGS = 'manage system settings';
    case VIEW_SYSTEM_ANALYTICS = 'view system analytics';
    case ACCESS_ADMIN_PANEL = 'access admin panel';
    case SUSPEND_USERS = 'suspend users';

    public function label(): string
    {
        return match ($this) {
            self::MANAGE_USERS => 'Manage Users',
            self::MANAGE_SYSTEM_SETTINGS => 'Manage System Settings',
            self::VIEW_SYSTEM_ANALYTICS => 'View System Analytics',
            self::ACCESS_ADMIN_PANEL => 'Access Admin Panel',
            self::SUSPEND_USERS => 'Suspend Users',
        };
    }

    public function category(): string
    {
        return match ($this) {
            self::MANAGE_USERS,
            self::SUSPEND_USERS => 'User Management',

            self::MANAGE_SYSTEM_SETTINGS,
            self::VIEW_SYSTEM_ANALYTICS,
            self::ACCESS_ADMIN_PANEL => 'System Administration',
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
