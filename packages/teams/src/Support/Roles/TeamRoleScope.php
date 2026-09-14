<?php

declare(strict_types=1);

namespace Concise\Teams\Support\Roles;

use App\Support\Roles\RoleScope;
use Concise\Teams\Enums\TeamPermission;
use Concise\Teams\Models\Team;
use Concise\Teams\Support\DomainPolicy;
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
     * Derived from the one definition, TeamPermission::implies(), which every
     * write path closes over (Team::createRole(), the Roles form on save).
     *
     * @return array<string, list<string>>
     */
    public function implications(): array
    {
        return TeamPermission::implicationMap();
    }

    /**
     * Manage Domains doesn't apply while the custom-domain overlay is off, so
     * the form doesn't offer it; a role that holds it (granted while the
     * feature was on) keeps it.
     *
     * @return list<string>
     */
    public function unavailable(): array
    {
        return DomainPolicy::enabled() ? [] : [TeamPermission::MANAGE_DOMAINS->value];
    }

    /** A shared team role, assignable in every team (members hold it through the membership pivot). */
    public function attributes(): array
    {
        return ['scope' => Team::ROLE_SCOPE];
    }

    public function order(): int
    {
        return 10;
    }
}
