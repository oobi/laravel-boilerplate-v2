<?php

declare(strict_types=1);

namespace Concise\Teams\Livewire\Admin\Teams;

use App\Actions\Impersonation\StartImpersonation;
use App\Enums\SystemPermission;
use App\Support\Theme\DaisyColor;
use Concise\Teams\Models\Team;
use Concise\Teams\Support\InvitationPolicy;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * System admin: a team's Overview tab — details and statistics. The other
 * tabs (Members, Invitations, Settings) are their own routes/components, one
 * per page like the profile screens. Gated by the `view teams` system
 * permission, never by membership.
 */
class ShowTeam extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    public Team $team;

    public function mount(Team $team): void
    {
        Gate::authorize(SystemPermission::VIEW_TEAMS->value);

        $this->team = $team;
    }

    public function teamInfolist(Schema $schema): Schema
    {
        return $schema
            ->record($this->team)
            ->components([
                Section::make(team_trans('admin.information'))
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('name')
                                    ->label(team_trans('admin.name')),

                                TextEntry::make('owner.name')
                                    ->label(team_trans('admin.primary_owner'))
                                    ->url(fn (Team $record): ?string => $record->owner ? route('users.show', $record->owner) : null)
                                    ->helperText(fn (Team $record): ?string => $record->owner?->email),

                                TextEntry::make('active')
                                    ->label(__('admin.status'))
                                    ->badge()
                                    ->formatStateUsing(fn (bool $state): string => $state ? team_trans('admin.active') : team_trans('admin.inactive'))
                                    ->color(fn (bool $state): string => ($state ? DaisyColor::SUCCESS : DaisyColor::NEUTRAL)->toFilamentColor()),

                                TextEntry::make('created_at')
                                    ->label(team_trans('admin.created'))
                                    ->dateTime(),

                                TextEntry::make('updated_at')
                                    ->label(team_trans('admin.updated'))
                                    ->dateTime(),
                            ]),
                    ]),
            ]);
    }

    /**
     * The team URL is members-only, so opening it from the admin screen adapts to
     * the viewer: a member goes straight in; a non-member who may impersonate the
     * owner is offered that (confirm → impersonate → land in the team as them);
     * anyone else is told, plainly, that they can't. Direct navigation to the team
     * host still just 403s — this is the sanctioned way in, from where you're looking.
     */
    public function openTeamAction(): Action
    {
        $team = $this->team;
        $viewer = auth()->user();
        $owner = $team->owner;

        $action = Action::make('openTeam')
            ->iconButton()
            ->icon('heroicon-o-arrow-top-right-on-square')
            ->tooltip(team_trans('admin.open'));

        if ($viewer !== null && $viewer->belongsToTeam($team)) {
            return $action
                ->url(team_route('team.dashboard', $team))
                ->openUrlInNewTab();
        }

        $action
            ->requiresConfirmation()
            ->modalIcon('heroicon-o-lock-closed')
            ->modalHeading(team_trans('open_team.private'));

        if ($owner !== null && Gate::allows('impersonate', $owner)) {
            // Land in *this* team as the owner, not their generic post-login home
            // (which, for an admin-capable owner, is the dashboard). Impersonation
            // happens in the action closure (this is the CSRF-protected Livewire
            // request), then the redirect goes straight to the team — decided here
            // in trusted code, never carried in a URL. The redirect must go through
            // successRedirectUrl — a redirect() returned from the action closure is
            // dropped by callMountedAction. Param MUST be named $action: Filament
            // injects the submit action by name; another name type-resolves to the
            // parent (icon) action, and only the confirm button is warning-tinted.
            return $action
                ->modalDescription(team_trans('open_team.impersonate_body', ['owner_name' => $owner->name]))
                ->modalSubmitAction(fn (Action $action) => $action
                    ->label(team_trans('open_team.impersonate'))
                    ->color(DaisyColor::WARNING->toFilamentColor()))
                ->action(fn () => app(StartImpersonation::class)->handle($owner, route('teams.show', $team)))
                ->successRedirectUrl(fn (): string => team_route('team.dashboard', $team));
        }

        return $action
            ->modalDescription(team_trans('open_team.not_member'))
            ->modalSubmitAction(false);
    }

    public function render(): View
    {
        return view('teams::livewire.admin.teams.show-team', [
            'memberCount' => $this->team->users()->count(),
            'ownerCount' => $this->team->ownerIds()->count(),
            'suspendedCount' => $this->team->users()->wherePivotNotNull('suspended_at')->count(),
            'showInvitations' => InvitationPolicy::adminsMayInvite(),
            'invitationCount' => $this->team->invitations()->count(),
        ]);
    }
}
