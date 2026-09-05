<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Application-wide (non-team) permissions, granted via a SystemRole.
 * Per-instance User abilities (edit, activate/deactivate, delete, ...) live
 * in App\Policies\UserPolicy instead — see .ai/rules/providers.md.
 */
enum SystemPermission: string
{
    /** Only remaining consumer: ListUsers::emptyTrashPermission() (a bulk, non-instance action). */
    case MANAGE_USERS = 'manage users';
    case MANAGE_SYSTEM_SETTINGS = 'manage system settings';
    case VIEW_SYSTEM_ANALYTICS = 'view system analytics';
    case ACCESS_ADMIN_PANEL = 'access admin panel';

    public function label(): string
    {
        return match ($this) {
            self::MANAGE_USERS => 'Manage Users',
            self::MANAGE_SYSTEM_SETTINGS => 'Manage System Settings',
            self::VIEW_SYSTEM_ANALYTICS => 'View System Analytics',
            self::ACCESS_ADMIN_PANEL => 'Access Admin Panel',
        };
    }

    public function category(): string
    {
        return match ($this) {
            self::MANAGE_USERS => 'User Management',

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
