<?php

declare(strict_types=1);

namespace Concise\Teams\Concerns;

use Concise\Teams\Models\Membership;
use Concise\Teams\Models\Team;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The one team-aware seam on the core User model (see ~dev/TEAMS_TIER_SCOPE.md).
 * Added by the teams tier and fenced in User.php so an uninstall can strip it
 * cleanly; core stays teams-agnostic without it.
 *
 * @property-read Team|null $currentTeam
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

    /** The user's active team context, if any (users may belong to zero teams). */
    public function currentTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'current_team_id');
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

    public function isCurrentTeam(Team $team): bool
    {
        return $this->current_team_id === $team->getKey();
    }

    /** Point the user at a team they belong to. Returns false if they don't. */
    public function switchTeam(Team $team): bool
    {
        if (! $this->belongsToTeam($team)) {
            return false;
        }

        $this->forceFill(['current_team_id' => $team->getKey()])->save();
        $this->setRelation('currentTeam', $team);

        return true;
    }
}
