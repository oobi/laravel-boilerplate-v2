<?php

declare(strict_types=1);

namespace Concise\Teams\Support\Roles;

use App\Support\Roles\RoleScope;
use Concise\Teams\Enums\TeamPermission;
use Concise\Teams\Models\Team;
use Concise\Teams\Support\TeamLabels;

/**
 * The teams tier's tab on the admin Roles screen: the centrally-defined roles
 * a member may hold in a team, with the TeamPermission vocabulary. Registered
 * from TeamsServiceProvider — core's roles components and views stay untouched.
 */
final class TeamRoleScope implements RoleScope
{
    public function key(): string
    {
        return Team::ROLE_SCOPE;
    }

    public function label(): string
    {
        return TeamLabels::singular();
    }

    public function description(): string
    {
        return team_trans('roles.scope_description');
    }

    public function permissions(): array
    {
        return collect(TeamPermission::byCategory())
            ->map(fn (array $permissions): array => collect($permissions)
                ->mapWithKeys(fn (TeamPermission $permission): array => [$permission->value => $permission->label()])
                ->all())
            ->all();
    }

    /**
     * A "manage/update" permission implies its "view" counterpart — you can't
     * meaningfully edit what you can't see — so ticking Update/Manage auto-ticks
     * View. Mirrors the same implications enforced in TeamPolicy.
     *
     * @return array<string, list<string>>
     */
    public function implications(): array
    {
        return [
            TeamPermission::MANAGE_MEMBERS->value => [TeamPermission::VIEW_MEMBERS->value],
            TeamPermission::UPDATE_TEAM->value => [TeamPermission::VIEW_SETTINGS->value],
            TeamPermission::MANAGE_DOMAINS->value => [TeamPermission::VIEW_SETTINGS->value],
        ];
    }

    /** A shared team role: `team_id` NULL resolves in every team's spatie scope (TEAMS_TIER_SCOPE.md §5). */
    public function attributes(): array
    {
        return [
            'scope' => Team::ROLE_SCOPE,
            'team_id' => null,
        ];
    }

    public function order(): int
    {
        return 10;
    }
}
