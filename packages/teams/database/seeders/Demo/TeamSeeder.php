<?php

declare(strict_types=1);

namespace Concise\Teams\Database\Seeders\Demo;

use App\Models\User;
use Concise\Teams\Models\Team;
use Concise\Teams\Support\TeamLabels;
use Concise\Teams\TeamsServiceProvider;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;

/**
 * Demo teams — the team counterpart of Demo\UserSeeder. Five teams, each with
 * an owner and five members spread across whatever team roles exist (the
 * TeamRolesSeeder defaults, or whatever an admin has since made of them).
 * Consecutive teams' member windows overlap so the demo shows users belonging
 * to several teams with a different role in each. Runs after Demo\UserSeeder,
 * so the first teams belong to the named demo accounts.
 */
class TeamSeeder extends Seeder
{
    private const TEAMS = 5;

    private const MEMBERS_PER_TEAM = 5;

    /** How many members consecutive teams share. */
    private const OVERLAP = 2;

    public function run(): void
    {
        if (! TeamsServiceProvider::isActive()) {
            $this->command?->warn('Teams tier is not active — skipping demo teams.');

            return;
        }

        $roles = Team::availableRoles()->orderBy('name')->pluck('name');
        $stride = self::MEMBERS_PER_TEAM - self::OVERLAP;
        $poolSize = ($stride * (self::TEAMS - 1)) + self::MEMBERS_PER_TEAM;

        // The seeded team-admin role, if it still exists — given to each owner so
        // the demo makes operational sense: an owner shown with the top role, not a
        // bare owner badge (and, under `managed` ownership, not powerless). An owner
        // may hold a role like any member; it sits alongside their owner badge.
        $adminRole = TeamLabels::singular().' Admin';
        $adminRole = $roles->contains($adminRole) ? $adminRole : null;

        $users = $this->users(self::TEAMS + $poolSize);
        $owners = $users->take(self::TEAMS)->values();
        $pool = $users->slice(self::TEAMS)->values();

        $teams = $owners->map(function (User $owner, int $index) use ($pool, $roles, $stride, $adminRole): Team {
            $team = Team::factory()->ownedBy($owner)->create();

            if ($adminRole !== null) {
                $team->syncMemberRoles($owner, [$adminRole]);
            }

            $pool->slice($index * $stride, self::MEMBERS_PER_TEAM)->values()
                ->each(fn (User $member, int $position) => $team->addMember(
                    $member,
                    $roles->get($position % max($roles->count(), 1)),
                ));

            return $team;
        });

        // The first owner (the super admin in the demo) also sits in the second
        // team as an ordinary member, so switching between "mine" and "theirs"
        // is one login away.
        if ($teams->count() > 1) {
            $teams->get(1)->addMember($owners->first(), $roles->first());
        }
    }

    /**
     * The first N users by id, topping up from the factory if the pool is short.
     *
     * @return Collection<int, User>
     */
    private function users(int $count): Collection
    {
        $shortfall = $count - User::query()->count();

        if ($shortfall > 0) {
            User::factory($shortfall)->create();
        }

        return User::query()->orderBy('id')->limit($count)->get();
    }
}
