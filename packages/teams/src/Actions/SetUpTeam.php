<?php

declare(strict_types=1);

namespace Concise\Teams\Actions;

use App\Models\Role;
use Concise\Teams\Models\Team;
use Concise\Teams\Support\TeamContext;

/**
 * Makes a newly created team self-consistent: seeds its team-scoped roles
 * (config('teams.roles')), adds the owner as a member, and grants the owner the
 * first role (owner). Runs in the team's permission scope so every role/assignment
 * is stored against this team, not the system scope (see ~dev/TEAMS_TIER_SCOPE.md §5).
 */
class SetUpTeam
{
    public function __invoke(Team $team): void
    {
        $roleNames = config('teams.roles', ['owner', 'admin', 'member']);

        app(TeamContext::class)->run($team, function () use ($team, $roleNames): void {
            collect($roleNames)->each(fn (string $name) => Role::findOrCreate($name));

            $team->users()->syncWithoutDetaching([$team->user_id]);

            $owner = $team->owner;
            $owner->unsetRelation('roles');

            if (! $owner->hasRole($roleNames[0])) {
                $owner->assignRole($roleNames[0]);
            }
        });
    }
}
