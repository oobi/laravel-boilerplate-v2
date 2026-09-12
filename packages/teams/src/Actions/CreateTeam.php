<?php

declare(strict_types=1);

namespace Concise\Teams\Actions;

use App\Models\User;
use Concise\Teams\Enums\TeamAbility;
use Concise\Teams\Exceptions\DefaultOwnerRoleMissing;
use Concise\Teams\Models\Team;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Self-service team creation: the write boundary behind the "Create a team"
 * modal. The creator becomes the primary owner (and, via the Team::created
 * hook, its first member) and the new team becomes their current one.
 *
 * Re-checks the policy rather than trusting the caller, and throttles per
 * user — self-service plus an open signup plus an unlimited default is
 * otherwise an obvious abuse surface.
 */
class CreateTeam
{
    /** Creations allowed per user per hour, whatever the owned-teams limit is. */
    private const PER_HOUR = 10;

    /**
     * @throws AuthorizationException when the creation mode or the owned limit forbids it
     * @throws DefaultOwnerRoleMissing when no team role exists to give the new owner
     * @throws ThrottleRequestsException when the user has created too many teams in the last hour
     */
    public function __invoke(string $name, User $owner): Team
    {
        if (! Gate::forUser($owner)->allows(TeamAbility::CREATE, Team::class)) {
            throw new AuthorizationException(team_trans('create.not_allowed'));
        }

        // Ownership grants no permissions, so a team whose owner can't be given
        // the default owner role would be born with nobody able to run it.
        if (! Team::defaultOwnerRoleExists()) {
            throw DefaultOwnerRoleMissing::make(Team::defaultOwnerRole());
        }

        $key = 'create-team:'.$owner->getKey();

        if (RateLimiter::tooManyAttempts($key, self::PER_HOUR)) {
            throw new ThrottleRequestsException(team_trans('create.throttled'));
        }

        RateLimiter::increment($key, 3600);

        $team = Team::create([
            'name' => $name,
            'user_id' => $owner->getKey(),
            'active' => true,
        ]);

        $owner->switchTeam($team);

        return $team;
    }
}
