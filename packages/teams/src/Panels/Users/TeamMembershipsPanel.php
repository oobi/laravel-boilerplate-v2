<?php

declare(strict_types=1);

namespace Concise\Teams\Panels\Users;

use App\Enums\SystemPermission;
use App\Models\Role;
use App\Models\User;
use App\Support\Panels\Concerns\HasPanelMetadata;
use App\Support\Panels\Contracts\ShowPanel;
use App\Support\Theme\DaisyColor;
use Concise\Teams\Models\Team;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * The teams a user belongs to, with their standing in each (primary owner,
 * owner, or role badges), on the admin User show page. Registered from
 * TeamsServiceProvider via PanelRegistry — the tier's card on a core page
 * without touching ShowUser or AdminPanels.
 */
class TeamMembershipsPanel implements ShowPanel
{
    use HasPanelMetadata;

    public function order(): int
    {
        return 5;
    }

    public function render(Model $subject): View
    {
        /** @var User $subject */
        $roles = Team::availableRoles()->get()->keyBy('name');

        $memberships = $subject->teams()->orderBy('name')->get()
            ->map(fn (Team $team): array => [
                'team' => $team,
                'badges' => $this->badgesFor($team, $subject, $roles),
            ]);

        return view('teams::panels.users.team-memberships', [
            'memberships' => $memberships,
            'canManage' => Gate::allows(SystemPermission::MANAGE_TEAMS->value),
        ]);
    }

    /**
     * @param  Collection<string, Role>  $roles
     * @return list<array{label: string, color: string}>
     */
    private function badgesFor(Team $team, User $user, $roles): array
    {
        $standing = match (true) {
            $team->isPrimaryOwner($user) => [['label' => team_trans('members.primary_owner'), 'color' => DaisyColor::SUCCESS->value]],
            $team->isOwnedBy($user) => [['label' => team_trans('members.owner'), 'color' => DaisyColor::SUCCESS->value]],
            default => $team->rolesFor($user)
                ->map(fn (string $name): array => [
                    'label' => $name,
                    'color' => ($roles->get($name)?->badgeColor() ?? DaisyColor::NEUTRAL)->value,
                ])
                ->whenEmpty(fn () => collect([['label' => team_trans('members.no_role'), 'color' => DaisyColor::NEUTRAL->value]]))
                ->all(),
        };

        if ($team->isSuspended($user)) {
            $standing[] = ['label' => team_trans('members.suspended'), 'color' => DaisyColor::WARNING->value];
        }

        return $standing;
    }
}
