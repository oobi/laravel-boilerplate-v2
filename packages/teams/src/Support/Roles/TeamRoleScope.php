<?php

declare(strict_types=1);

namespace Concise\Teams\Support\Roles;

use App\Enums\SystemPermission;
use App\Models\Role;
use App\Models\User;
use App\Support\Roles\RoleScope;
use Concise\Teams\Enums\TeamPermission;
use Concise\Teams\Models\Team;
use Concise\Teams\Support\DomainPolicy;
use Concise\Teams\Support\TeamLabels;
use Illuminate\Support\Facades\DB;

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
     * Manage Domains doesn't apply while the custom-domains tier is off, so the
     * form doesn't offer it; a role that holds it (granted while the feature was
     * on) keeps it.
     *
     * @return list<string>
     */
    public function unavailable(): array
    {
        return DomainPolicy::customDomainsEnabled() ? [] : [TeamPermission::MANAGE_DOMAINS->value];
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

    /**
     * Only head office running teams ("manage teams"). Team roles are shared by
     * every team and someone with "manage roles" may hold one, so editing them
     * could otherwise raise their own access; "manage teams" already grants
     * every team ability (TeamPolicy::before()), so it gains them nothing.
     */
    public function mayManage(User $actor): bool
    {
        return $actor->hasSystemPermission(SystemPermission::MANAGE_TEAMS);
    }

    /** Memberships holding the role, across every team (team roles live on the membership, not the user). */
    public function holderCount(Role $role): int
    {
        return DB::table('team_user_role')->where('role_id', $role->getKey())->count();
    }
}
