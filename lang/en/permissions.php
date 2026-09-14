<?php

declare(strict_types=1);

/*
 * Display strings for App\Enums\SystemPermission — labels and category headings
 * shown on the Manage Roles screen. These are cosmetic: the permission *value*
 * ('manage users', …) is the fixed identifier used by policies, seeders and the
 * role_has_permissions table, and never changes. Only what an admin reads does,
 * so a localised install translates here without touching authorization.
 *
 * The teams tier's team permissions relabel through its own lang file
 * (team_trans), so they aren't here.
 */

return [

    'labels' => [
        'access_admin_panel' => 'Access Admin Panel',
        'manage_system_settings' => 'Manage System Settings',
        'view_system_analytics' => 'View System Analytics',
        'view_users' => 'View Users',
        'manage_users' => 'Manage Users',
        'suspend_users' => 'Suspend Users',
        'delete_users' => 'Delete Users',
        'impersonate_users' => 'Impersonate Users',
    ],

    'categories' => [
        'user_management' => 'User Management',
        'system_administration' => 'System Administration',
    ],

];
