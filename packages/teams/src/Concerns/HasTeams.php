<?php

declare(strict_types=1);

namespace Concise\Teams\Concerns;

use Concise\Teams\Models\Membership;
use Concise\Teams\Models\Team;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The one team-aware seam on the core User model. Added by the teams tier and
 * fenced in User.php so an uninstall can strip it cleanly (see
 * docs/teams-distribution.md); core stays teams-agnostic without it.
 *
 * There is deliberately no "current team" pointer on the user: the active team
 * is always resolved from the request (the {team} slug in path mode, the host in
 * host mode) into the request-scoped TeamContext, never stored. A per-user column
 * would be a single shared value that couldn't represent one person working in
 * different teams in different tabs/devices. "Remember my last team" is a
 * per-device concern (cookie/session), not database state — see TeamDestination.
 */
trait HasTeams
{
    /** Teams this user is a member of (owner rows are also members). */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'team_user')
            ->using(Membership::class)
            ->withTimestamps();
    }

    /** Teams this user owns. */
    public function ownedTeams(): HasMany
    {
        return $this->hasMany(Team::class, 'user_id');
    }

    /** Active membership — the access check. A suspended member is still listed in teams() but not "belonging" for access. */
    public function belongsToTeam(Team $team): bool
    {
        return $team->isActiveMember($this);
    }

    /**
     * This user's membership row in a team, with its roles, memoised per team
     * on this instance. The authenticated user is one instance for a whole
     * request, so every "may I do X in this team?" a page asks — nav items,
     * buttons, table-row actions, across however many Team instances the
     * request holds — is answered from one lookup. This is spatie's own
     * pattern (roles are loadMissing()'d once per user instance; the
     * role → permission map comes from its cache), applied to the team pivot.
     * Team's write methods forget the entry; so does refresh().
     *
     * @var array<int|string, Membership|null>
     */
    protected array $teamMemberships = [];

    public function membershipIn(Team $team): ?Membership
    {
        $key = $team->getKey();

        if (! array_key_exists($key, $this->teamMemberships)) {
            $this->teamMemberships[$key] = Membership::query()
                ->with('roles')
                ->where('team_id', $key)
                ->where('user_id', $this->getKey())
                ->first();
        }

        return $this->teamMemberships[$key];
    }

    /** Drop the memoised membership for a team, so the next question re-reads the row. */
    public function forgetMembershipIn(Team $team): void
    {
        unset($this->teamMemberships[$team->getKey()]);
    }

    /** Reloading the user also forgets its memoised team memberships. */
    public function refresh(): static
    {
        $this->teamMemberships = [];

        return parent::refresh();
    }

    /**
     * The teams this user can actually enter: active teams they're an active
     * (unsuspended) member of. What the switcher and /{prefix} entry offer.
     */
    public function accessibleTeams(): BelongsToMany
    {
        return $this->teams()->active()->wherePivotNull('suspended_at');
    }
}
