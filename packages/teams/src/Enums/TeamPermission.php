<?php

declare(strict_types=1);

namespace Concise\Teams\Enums;

/**
 * The fixed, code-checked vocabulary of team-level capabilities — the team
 * counterpart of App\Enums\SystemPermission. Each case is a real TeamPolicy
 * check and is seeded as a spatie Permission row (TeamRolesSeeder). Which
 * team roles hold which permission is admin-configurable, never a hardcoded
 * match(). Ownership grants none of these: the primary owner's non-delegable
 * acts (co-owners, transfer, delete) are granted in TeamPolicy::before, and
 * everything else an owner does comes from their role like any member. A
 * project extends this enum with its own domain capabilities the same way it
 * extends SystemPermission.
 */
enum TeamPermission: string
{
    case VIEW_MEMBERS = 'view team members';
    case MANAGE_MEMBERS = 'manage team members';
    case INVITE_MEMBERS = 'invite team members';
    case VIEW_SETTINGS = 'view team settings';
    case UPDATE_TEAM = 'update team';
    case MANAGE_DOMAINS = 'manage team domains';

    public function label(): string
    {
        return match ($this) {
            self::VIEW_MEMBERS => team_trans('permissions.view_members'),
            self::MANAGE_MEMBERS => team_trans('permissions.manage_members'),
            self::INVITE_MEMBERS => team_trans('permissions.invite_members'),
            self::VIEW_SETTINGS => team_trans('permissions.view_settings'),
            self::UPDATE_TEAM => team_trans('permissions.update_team'),
            self::MANAGE_DOMAINS => team_trans('permissions.manage_domains'),
        };
    }

    public function category(): string
    {
        return match ($this) {
            self::VIEW_MEMBERS,
            self::MANAGE_MEMBERS,
            self::INVITE_MEMBERS => team_trans('permissions.category_members'),

            self::VIEW_SETTINGS,
            self::UPDATE_TEAM,
            self::MANAGE_DOMAINS => team_trans('permissions.category_settings'),
        };
    }

    /**
     * The permissions offered on the Roles screen for the current config —
     * feature-gated ones (MANAGE_DOMAINS) are hidden while their feature is off,
     * so a permission for an inactive feature never appears. The full enum stays
     * the fixed vocabulary; this is just the relevant view of it. (Seeding still
     * creates every row so the permission is assignable the moment it's enabled.)
     *
     * @return list<self>
     */
    public static function available(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $permission): bool => $permission !== self::MANAGE_DOMAINS
                || (bool) config('teams.domains.enabled', false),
        ));
    }

    /** @return array<string, list<TeamPermission>> */
    public static function byCategory(): array
    {
        $categories = [];

        foreach (self::available() as $permission) {
            $categories[$permission->category()][] = $permission;
        }

        return $categories;
    }
}
