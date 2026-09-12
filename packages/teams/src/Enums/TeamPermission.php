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
 * everything else an owner does comes from their role like any member.
 *
 * Implications ("manage implies view") are declared once, here (implies()),
 * and enforced once, in Team::memberHasPermission(); the Roles form only
 * displays them. A feature-gated case (MANAGE_DOMAINS) is always part of the
 * vocabulary; while its feature is off the form doesn't offer it
 * (TeamRoleScope::unavailable()), the seeder doesn't grant it, and a role
 * that already holds it keeps it across a save.
 *
 * A project extends this enum with its own domain capabilities the same way
 * it extends SystemPermission.
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
     * The permissions this one carries with it: you can't meaningfully edit
     * what you can't see, so a manage/update grants its view counterpart.
     * Single level — these don't chain.
     *
     * @return list<self>
     */
    public function implies(): array
    {
        return match ($this) {
            self::MANAGE_MEMBERS => [self::VIEW_MEMBERS],
            self::UPDATE_TEAM, self::MANAGE_DOMAINS => [self::VIEW_SETTINGS],
            default => [],
        };
    }

    /**
     * Every permission that carries the given one with it — what a check for
     * `$permission` also accepts.
     *
     * @return list<self>
     */
    public static function impliedBy(self $permission): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $case): bool => in_array($permission, $case->implies(), true),
        ));
    }

    /**
     * implies() as the Roles form consumes it: permission name => implied names.
     *
     * @return array<string, list<string>>
     */
    public static function implicationMap(): array
    {
        return collect(self::cases())
            ->filter(fn (self $case): bool => $case->implies() !== [])
            ->mapWithKeys(fn (self $case): array => [
                $case->value => array_map(fn (self $implied): string => $implied->value, $case->implies()),
            ])
            ->all();
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
