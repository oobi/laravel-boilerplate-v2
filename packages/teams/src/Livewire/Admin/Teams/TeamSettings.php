<?php

declare(strict_types=1);

namespace Concise\Teams\Livewire\Admin\Teams;

use App\Enums\SystemPermission;
use App\Support\Filament\AdminAction;
use App\Support\Theme\DaisyColor;
use Concise\Teams\Enums\TeamAbility;
use Concise\Teams\Models\Team;
use Concise\Teams\Support\DomainPolicy;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * System admin: a team's Settings tab — the edit form, and the danger zone.
 * Opens read-only for `view teams`; the form edits with `manage teams`, and
 * each danger-zone act has its own permission (`deactivate teams`, `delete
 * teams`, ownership through TeamPolicy). Active/inactive is set ONLY by the
 * deactivate action — it isn't a form field, so `manage teams` alone can't
 * flip it by saving the form.
 *
 * Taking a team out of service is a two-step, reversible act: deactivate
 * (members keep their membership, can't enter), then delete (soft —
 * restorable from the Teams list's trash view). Delete stays disabled, with a
 * "deactivate first" tooltip, while the team is active.
 */
class TeamSettings extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    public Team $team;

    /** @var array<string, mixed> */
    public ?array $data = [];

    public function mount(Team $team): void
    {
        Gate::authorize(SystemPermission::VIEW_TEAMS->value);

        $this->team = $team;

        $this->form->fill([
            'name' => $team->name,
            'slug' => $team->slug,
        ]);
    }

    /** May the viewer edit team details (else the form is read-only)? */
    public function canUpdate(): bool
    {
        return Gate::allows(SystemPermission::MANAGE_TEAMS->value);
    }

    /** Whether to render the ownership section: `manage team ownership`, via TeamPolicy::before. The child aborts otherwise. */
    public function canManageOwnership(): bool
    {
        return Gate::allows(TeamAbility::MANAGE_OWNERS, $this->team);
    }

    public function canDeactivate(): bool
    {
        return Gate::allows(SystemPermission::DEACTIVATE_TEAMS->value);
    }

    public function canDelete(): bool
    {
        return Gate::allows(SystemPermission::DELETE_TEAMS->value);
    }

    /** The danger-zone card exists only for a viewer who may do something in it; a read-only viewer gets the full-width form. */
    public function showDangerZone(): bool
    {
        return $this->canManageOwnership() || $this->canDeactivate() || $this->canDelete();
    }

    /** Whether to render the domains section: the feature on and `manage teams`, via TeamPolicy::before. The child aborts otherwise. */
    public function canViewDomains(): bool
    {
        return DomainPolicy::enabled() && Gate::allows(TeamAbility::MANAGE_DOMAINS, $this->team);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->record($this->team)
            ->disabled(fn (): bool => ! $this->canUpdate())
            ->components([
                Section::make(team_trans('admin.details'))
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->label(team_trans('admin.name'))
                                    ->required()
                                    ->maxLength(255),

                                TextInput::make('slug')
                                    ->label(team_trans('admin.slug'))
                                    ->helperText(team_trans('admin.slug_help'))
                                    ->required()
                                    ->maxLength(255)
                                    ->alphaDash()
                                    ->unique(table: 'teams', column: 'slug', ignoreRecord: true),
                            ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        Gate::authorize(SystemPermission::MANAGE_TEAMS->value);

        $data = $this->form->getState();

        $this->team->update([
            'name' => $data['name'],
            'slug' => $data['slug'],
        ]);

        Notification::make()->title(team_trans('admin.updated_notice'))->success()->send();

        // The slug is the URL; stay on this page even if it changed.
        $this->redirect(route('teams.settings', $this->team));
    }

    public function toggleActiveAction(): Action
    {
        return AdminAction::make('toggleActive')
            ->label(fn (): string => $this->team->active ? __('admin.deactivate') : __('admin.activate'))
            ->icon(fn (): string => $this->team->active ? 'heroicon-o-pause-circle' : 'heroicon-o-check-circle')
            ->soft()
            ->color(fn (): string => ($this->team->active ? DaisyColor::WARNING : DaisyColor::SUCCESS)->toFilamentColor())
            ->authorize(fn (): bool => $this->canDeactivate())
            ->requiresConfirmation()
            ->modalDescription(fn (): string => $this->team->active
                ? team_trans('admin.deactivate_confirm', ['name' => $this->team->name])
                : team_trans('admin.reactivate_confirm', ['name' => $this->team->name]))
            ->action(function (): void {
                Gate::authorize(SystemPermission::DEACTIVATE_TEAMS->value);

                $this->team->update(['active' => ! $this->team->active]);

                Notification::make()
                    ->title($this->team->active
                        ? team_trans('admin.activated', ['name' => $this->team->name])
                        : team_trans('admin.deactivated', ['name' => $this->team->name]))
                    ->success()
                    ->send();
            });
    }

    public function deleteTeamAction(): Action
    {
        return AdminAction::make('deleteTeam')
            ->label(team_trans('admin.delete'))
            ->icon('heroicon-o-trash')
            ->soft()
            ->color(DaisyColor::ERROR->toFilamentColor())
            ->authorize(fn (): bool => $this->canDelete())
            ->disabled(fn (): bool => $this->team->active)
            ->tooltip(fn (): ?string => $this->team->active ? team_trans('admin.deactivate_first') : null)
            ->requiresConfirmation()
            ->modalDescription(fn (): string => Team::deleteWarning($this->team))
            ->action(function (): void {
                Gate::authorize(SystemPermission::DELETE_TEAMS->value);
                abort_if($this->team->active, 403, team_trans('admin.deactivate_before_delete'));

                $this->team->delete();

                Notification::make()->title(team_trans('admin.deleted_notice'))->success()->send();

                $this->redirect(route('teams.index'));
            });
    }

    public function render(): View
    {
        return view('teams::livewire.admin.teams.team-settings');
    }
}
