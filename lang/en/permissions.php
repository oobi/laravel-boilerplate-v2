<?php

declare(strict_types=1);

/*
 * Display strings for App\Enums\SystemPermission — labels and category headings
 * shown on the Manage roles screen. These are cosmetic: the permission *value*
 * ('manage users', …) is the fixed identifier used by policies, seeders and the
 * role_has_permissions table, and never changes. Only what an admin reads does,
 * so a localised install translates here without touching authorization.
 *
 * The teams tier's team permissions relabel through its own lang file
 * (team_trans), so they aren't here.
 */

return [

    'labels' => [
        'access_admin_panel' => 'Access admin panel',
        'view_users' => 'View users',
        'manage_users' => 'Manage users',
        'suspend_users' => 'Suspend users',
        'delete_users' => 'Delete users',
        'impersonate_users' => 'Impersonate users',
        'set_user_passwords' => 'Set user passwords',
        'manage_roles' => 'Manage roles',
    ],

    'categories' => [
        'user_management' => 'User management',
        'system_administration' => 'System administration',
    ],

];
