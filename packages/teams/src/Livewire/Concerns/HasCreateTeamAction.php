<?php

declare(strict_types=1);

namespace Concise\Teams\Livewire\Concerns;

use Concise\Teams\Actions\CreateTeam;
use Concise\Teams\Enums\TeamAbility;
use Concise\Teams\Models\Team;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\Gate;

/**
 * The "create a team" modal, shared by the zero-team onboarding page and the
 * team select panel. Asks for a name only — the slug is derived from it and
 * is editable later in settings, which isn't a decision anyone can make well
 * at this point. Visibility and the write both ask TeamPolicy::create.
 */
trait HasCreateTeamAction
{
    public function createTeamAction(): Action
    {
        return Action::make('createTeam')
            ->label(team_trans('create.action'))
            ->icon('heroicon-o-plus')
            ->modalHeading(team_trans('create.heading'))
            ->modalSubmitActionLabel(team_trans('create.submit'))
            ->modalWidth(Width::Medium)
            ->visible(fn (): bool => Gate::allows(TeamAbility::CREATE, Team::class))
            ->schema([
                TextInput::make('name')
                    ->label(team_trans('create.name'))
                    ->helperText(team_trans('create.name_help'))
                    ->required()
                    ->maxLength(255),
            ])
            ->action(function (array $data): void {
                Gate::authorize(TeamAbility::CREATE, Team::class);

                try {
                    $team = app(CreateTeam::class)($data['name'], auth()->user());
                } catch (ThrottleRequestsException) {
                    Notification::make()->title(team_trans('create.throttled'))->danger()->send();

                    return;
                }

                Notification::make()
                    ->title(team_trans('create.created', ['name' => $team->name]))
                    ->success()
                    ->send();

                $this->redirect(route('team.dashboard', ['team' => $team->slug]));
            });
    }
}
