<?php

declare(strict_types=1);

namespace Concise\Teams\Enums;

use App\Support\Roles\Implications;

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
 * and applied when a role is WRITTEN — Team::createRole() and the Roles form
 * both store the closure (withImplied()) — so what spatie stores is the truth:
 * `$role->checkPermissionTo()` and the Gate agree, and a check asks for exactly
 * one permission. The Roles form also displays them ("Included with …").
 *
 * What that closure IS in storage: a team role is a `roles` row (scope
 * `team`), a permission is a `permissions` row named by a case's value, and a
 * grant is a `role_has_permissions` row joining the two. "Closed" means that
 * for every grant row whose permission implies another, the role also has a
 * grant row for the implied one. Adding an entry to implies() later — say
 * INVITE_MEMBERS => [VIEW_MEMBERS] — leaves every team role that already has a
 * `role_has_permissions` row for "invite team members" but none for "view team
 * members" out of closure; the Gate would then refuse them the view. The fix
 * is those missing `role_has_permissions` rows, and
 * `php artisan bp:roles:sync-implications` inserts exactly them: for each
 * team role, the implied permissions it lacks (creating the `permissions` row
 * first if it doesn't exist yet). It never deletes a row, so REMOVING an
 * entry from implies() needs no fix — the role keeps its existing grant as an
 * ordinary one. Idempotent; `--dry-run` lists the roles and rows it would
 * add. Run it with the deploy that changes this map.
 *
 * A feature-gated case (MANAGE_DOMAINS) is always part of the
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
     * The permissions this one carries with it, one hop: you can't
     * meaningfully edit what you can't see, so a manage/update grants its view
     * counterpart. withImplied() follows the chain if one is ever declared.
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
     * A grant closed over its implications, transitively — what every write
     * path stores. Order preserved, no duplicates.
     *
     * @param  list<self>  $permissions
     * @return list<self>
     */
    public static function withImplied(array $permissions): array
    {
        return collect(Implications::close(
            self::implicationMap(),
            array_map(fn (self $permission): string => $permission->value, $permissions),
        ))->map(fn (string $name): self => self::from($name))->all();
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
