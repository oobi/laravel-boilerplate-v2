<?php

declare(strict_types=1);

namespace Concise\Teams\Livewire\Admin\Teams;

use App\Enums\SystemPermission;
use App\Support\Filament\AdminAction;
use App\Support\Theme\DaisyColor;
use Concise\Teams\Models\Team;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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
        Gate::authorize(SystemPermission::MANAGE_TEAMS->value);

        $this->team = $team;

        $this->form->fill([
            'name' => $team->name,
            'slug' => $team->slug,
            'active' => $team->active,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->record($this->team)
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

                        Toggle::make('active')
                            ->label(team_trans('admin.active'))
                            ->helperText(team_trans('admin.active_help')),
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
            'active' => (bool) $data['active'],
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
            ->requiresConfirmation()
            ->modalDescription(fn (): string => $this->team->active
                ? team_trans('admin.deactivate_confirm', ['name' => $this->team->name])
                : team_trans('admin.reactivate_confirm', ['name' => $this->team->name]))
            ->action(function (): void {
                Gate::authorize(SystemPermission::MANAGE_TEAMS->value);

                $this->team->update(['active' => ! $this->team->active]);
                $this->form->fill([...$this->data, 'active' => $this->team->active]);

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
            ->disabled(fn (): bool => $this->team->active)
            ->tooltip(fn (): ?string => $this->team->active ? team_trans('admin.deactivate_first') : null)
            ->requiresConfirmation()
            ->modalDescription(fn (): string => Team::deleteWarning($this->team))
            ->action(function (): void {
                Gate::authorize(SystemPermission::MANAGE_TEAMS->value);
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
