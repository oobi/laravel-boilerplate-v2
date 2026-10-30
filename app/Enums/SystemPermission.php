<?php

declare(strict_types=1);

namespace App\Enums;

use App\Support\Roles\SystemPermissionDefinition;

/**
 * The fixed, code-checked vocabulary of application-wide capabilities. Each
 * case corresponds to a real Gate/Policy check somewhere in the app and is
 * seeded as a spatie/laravel-permission Permission row (see PermissionSeeder)
 * — role -> permission *assignment* is admin-configurable via the Roles
 * screen, never a hardcoded match(). See .ai/rules/providers.md.
 *
 * Shape: `access admin panel` is entry only — the shell, the dashboard, the
 * navigation. Each admin area has its own read floor (`view users`, an add-on's
 * `view teams`) so a role can reach the panel for one
 * module without browsing another's data; the action permissions of an area
 * sit on top of its read floor. That shape is enforced by implies(): every
 * action carries its area's view, every view carries panel entry, applied when
 * a role is WRITTEN (SystemPermissionRegistry::withImplied()) so a stored
 * grant is always reachable and `checkPermissionTo()` and the Gate agree — see
 * App\Support\Roles\Implications and the `bp:roles:sync-implications` data
 * fix for a map that changes later.
 *
 * Core's cases only: an add-on declares its own enum implementing
 * SystemPermissionDefinition and registers it with SystemPermissionRegistry,
 * so nothing that needs the whole vocabulary reads cases() here.
 */
enum SystemPermission: string implements SystemPermissionDefinition
{
    case ACCESS_ADMIN_PANEL = 'access admin panel';
    case VIEW_USERS = 'view users';
    case MANAGE_USERS = 'manage users';
    case SUSPEND_USERS = 'suspend users';
    case DELETE_USERS = 'delete users';
    case IMPERSONATE_USERS = 'impersonate users';
    case SET_USER_PASSWORDS = 'set user passwords';
    case MANAGE_ROLES = 'manage roles';

    public function label(): string
    {
        return match ($this) {
            self::ACCESS_ADMIN_PANEL => __('permissions.labels.access_admin_panel'),
            self::VIEW_USERS => __('permissions.labels.view_users'),
            self::MANAGE_USERS => __('permissions.labels.manage_users'),
            self::SUSPEND_USERS => __('permissions.labels.suspend_users'),
            self::DELETE_USERS => __('permissions.labels.delete_users'),
            self::IMPERSONATE_USERS => __('permissions.labels.impersonate_users'),
            self::SET_USER_PASSWORDS => __('permissions.labels.set_user_passwords'),
            self::MANAGE_ROLES => __('permissions.labels.manage_roles'),
        };
    }

    public function category(): string
    {
        return match ($this) {
            self::VIEW_USERS,
            self::MANAGE_USERS,
            self::SUSPEND_USERS,
            self::DELETE_USERS,
            self::IMPERSONATE_USERS,
            self::SET_USER_PASSWORDS,
            self::MANAGE_ROLES => __('permissions.categories.user_management'),

            self::ACCESS_ADMIN_PANEL => __('permissions.categories.system_administration'),
        };
    }

    /**
     * What this permission carries with it, one hop: an action carries its
     * area's read floor, a read floor carries panel entry. Declared here once;
     * applied transitively by SystemPermissionRegistry::withImplied() at every
     * write (the seeder, the Roles form) and shown by the form ("Included with
     * …"). Why: a grant that can't be reached — `delete users` on a role that
     * can't open the users area — looks like a working role and isn't.
     *
     * @return list<SystemPermissionDefinition>
     */
    public function implies(): array
    {
        return match ($this) {
            self::VIEW_USERS => [self::ACCESS_ADMIN_PANEL],

            self::MANAGE_USERS,
            self::SUSPEND_USERS,
            self::DELETE_USERS,
            self::IMPERSONATE_USERS,
            self::SET_USER_PASSWORDS => [self::VIEW_USERS],

            self::MANAGE_ROLES => [self::ACCESS_ADMIN_PANEL],

            default => [],
        };
    }
}
