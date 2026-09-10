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
