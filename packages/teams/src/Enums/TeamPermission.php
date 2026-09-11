<?php

declare(strict_types=1);

namespace Concise\Teams\Enums;

/**
 * The fixed, code-checked vocabulary of team-level capabilities — the team
 * counterpart of App\Enums\SystemPermission. Each case is a real TeamPolicy
 * check and is seeded as a spatie Permission row (TeamRolesSeeder). Which
 * team roles hold which permission is admin-configurable, never a hardcoded
 * match(); owners bypass all of these within their own team, exactly as the
 * super admin bypasses SystemPermission. A project extends this enum with its
 * own domain capabilities the same way it extends SystemPermission.
 */
enum TeamPermission: string
{
    case MANAGE_MEMBERS = 'manage team members';
    case INVITE_MEMBERS = 'invite team members';
    case UPDATE_TEAM = 'update team';

    public function label(): string
    {
        return match ($this) {
            self::MANAGE_MEMBERS => team_trans('permissions.manage_members'),
            self::INVITE_MEMBERS => team_trans('permissions.invite_members'),
            self::UPDATE_TEAM => team_trans('permissions.update_team'),
        };
    }

    public function category(): string
    {
        return match ($this) {
            self::MANAGE_MEMBERS,
            self::INVITE_MEMBERS => team_trans('permissions.category_members'),

            self::UPDATE_TEAM => team_trans('permissions.category_settings'),
        };
    }

    /** @return array<string, list<TeamPermission>> */
    public static function byCategory(): array
    {
        $categories = [];

        foreach (self::cases() as $permission) {
            $categories[$permission->category()][] = $permission;
        }

        return $categories;
    }
}
