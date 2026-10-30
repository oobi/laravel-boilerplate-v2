<?php

declare(strict_types=1);

namespace Concise\Teams\Enums;

use App\Enums\SystemPermission;
use App\Support\Roles\SystemPermissionDefinition;

/**
 * The tier's system-level permissions: the "manage all teams" admin area,
 * held through a SYSTEM role and registered with core's
 * SystemPermissionRegistry from TeamsServiceProvider, so they list, seed and
 * imply like core's SystemPermission cases. Not to be confused with
 * TeamPermission (what a member's TEAM role grants inside one team) or
 * TeamAbility (TeamPolicy method names).
 *
 * `view teams` is the read floor (admin entry to the teams area, read-only);
 * `manage teams` the day-to-day edits (create, settings, members, roles,
 * invitations, domains); the destructive and ownership acts have their own
 * finer permissions, mirroring the user set. TeamPolicy::before() maps them to
 * abilities. Values are fixed identifiers; labels relabel with the tier.
 */
enum TeamSystemPermission: string implements SystemPermissionDefinition
{
    case VIEW_TEAMS = 'view teams';
    case MANAGE_TEAMS = 'manage teams';
    case DEACTIVATE_TEAMS = 'deactivate teams';
    case DELETE_TEAMS = 'delete teams';
    case MANAGE_TEAM_OWNERSHIP = 'manage team ownership';

    public function label(): string
    {
        return match ($this) {
            self::VIEW_TEAMS => team_trans('permissions.system_view_teams'),
            self::MANAGE_TEAMS => team_trans('permissions.system_manage_teams'),
            self::DEACTIVATE_TEAMS => team_trans('permissions.system_deactivate_teams'),
            self::DELETE_TEAMS => team_trans('permissions.system_delete_teams'),
            self::MANAGE_TEAM_OWNERSHIP => team_trans('permissions.system_manage_team_ownership'),
        };
    }

    /** The tier's own group on the Roles screen (relabels with the tier). */
    public function category(): string
    {
        return team_trans('permissions.category_teams');
    }

    /**
     * The read floor carries panel entry; every other case carries the read floor.
     *
     * @return list<SystemPermissionDefinition>
     */
    public function implies(): array
    {
        return match ($this) {
            self::VIEW_TEAMS => [SystemPermission::ACCESS_ADMIN_PANEL],
            default => [self::VIEW_TEAMS],
        };
    }
}
