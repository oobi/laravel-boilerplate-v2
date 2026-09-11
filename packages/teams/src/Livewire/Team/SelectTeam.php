<?php

declare(strict_types=1);

namespace Concise\Teams\Livewire\Team;

use App\Models\User;
use Concise\Teams\Enums\TeamAbility;
use Concise\Teams\Livewire\Concerns\HasCreateTeamAction;
use Concise\Teams\Models\Team;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The team picker: every team the user can enter, as cards with enough
 * context to tell them apart (their standing, how many members). Reached from
 * the entry point when there's no obvious team to open, and from the
 * switcher's "All teams" — the dropdown is for switching in one click, this
 * is for choosing. Creation lives here too, so a user who already has a team
 * still has a door (`?create=1` opens the modal straight away).
 */
#[Layout('teams::layouts.lobby')]
class SelectTeam extends Component implements HasActions, HasSchemas
{
    use HasCreateTeamAction;
    use InteractsWithActions;
    use InteractsWithSchemas;

    public const FILTER_ALL = 'all';

    public const FILTER_OWNED = 'owned';

    /** Everything they can enter, or only the teams they own. Survives a refresh via `?filter=owned`. */
    #[Url(except: self::FILTER_ALL)]
    public string $filter = self::FILTER_ALL;

    public function mount(): void
    {
        // Nothing to choose between: the zero-team page owns that empty state.
        if (auth()->user()->accessibleTeams()->doesntExist()) {
            $this->redirect(route('team.onboarding'));

            return;
        }

        if (request()->boolean('create') && Gate::allows(TeamAbility::CREATE, Team::class)) {
            $this->mountAction('createTeam');
        }
    }

    public function render(): View
    {
        $user = auth()->user();

        $allCount = $user->accessibleTeams()->count();
        $ownedCount = $this->owned($user->accessibleTeams(), $user)->count();

        return view('teams::livewire.team.select', [
            'teams' => $this->query($user)->withCount('users')->orderBy('name')->get(),
            'currentTeamId' => $user->current_team_id,
            'allCount' => $allCount,
            'ownedCount' => $ownedCount,
            // A toggle that would change nothing (or empty the page) is just noise.
            'showFilter' => $ownedCount > 0 && $ownedCount < $allCount,
        ]);
    }

    /** @return BelongsToMany<Team, User> */
    private function query(User $user): BelongsToMany
    {
        $teams = $user->accessibleTeams();

        return $this->filter === self::FILTER_OWNED ? $this->owned($teams, $user) : $teams;
    }

    /**
     * Teams the user owns — primary owner or co-owner, the two the cards badge
     * as "Owner" (see Team::isOwnedBy).
     *
     * @param  BelongsToMany<Team, User>  $teams
     * @return BelongsToMany<Team, User>
     */
    private function owned(BelongsToMany $teams, User $user): BelongsToMany
    {
        return $teams->where(fn (Builder $query) => $query
            ->where('teams.user_id', $user->getKey())
            ->orWhere('team_user.is_owner', true));
    }
}
