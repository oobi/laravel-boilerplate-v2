<?php

declare(strict_types=1);

namespace Concise\Teams\Actions;

use App\Models\User;
use Concise\Teams\Models\Team;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Add one user to several teams at once, with the same role(s) in each (the
 * admin User show page's "Add to teams"). The ids come from a picker whose
 * list is only presentational, so a team the user already belongs to, or an
 * id that isn't a live team, is skipped rather than trusted.
 */
class AddUserToTeams
{
    /**
     * @param  list<int|string>  $teamIds
     * @param  string|list<string>|null  $roles
     * @return Collection<int, Team> the teams the user was added to
     */
    public function __invoke(User $user, array $teamIds, string|array|null $roles): Collection
    {
        $teams = Team::query()
            ->whereKey($teamIds)
            ->whereDoesntHave('users', fn ($query) => $query->whereKey($user->getKey()))
            ->orderBy('name')
            ->get();

        DB::transaction(fn () => $teams->each(fn (Team $team) => $team->addMember($user, $roles)));

        return $teams;
    }
}
