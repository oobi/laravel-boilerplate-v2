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

    public function belongsToTeam(Team $team): bool
    {
        return $this->teams()->whereKey($team->getKey())->exists()
            || $team->user_id === $this->getKey();
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
