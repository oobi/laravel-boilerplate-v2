<?php

declare(strict_types=1);

namespace Concise\ThemeDemo\Livewire;

use App\Enums\SystemPermission;
use App\Support\Theme\DaisyColor;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * Filament modal gallery — the standard way to confirm an action in this app:
 * a Filament Action with ->requiresConfirmation(), plus the destructive variant
 * with custom copy and a modal that collects input via a schema before running.
 */
class FilamentModalGallery extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    public function mount(): void
    {
        Gate::authorize(SystemPermission::ACCESS_ADMIN_PANEL->value);
    }

    /** Plain "are you sure?" confirmation modal. */
    public function confirmAction(): Action
    {
        return Action::make('confirm')
            ->label(__('theme-demo::messages.modals_filament_confirm_label'))
            ->icon('heroicon-o-check-circle')
            ->requiresConfirmation()
            ->action(fn () => $this->notifyRan());
    }

    /** Destructive confirmation with custom heading, description and submit label. */
    public function destroyAction(): Action
    {
        return Action::make('destroy')
            ->label(__('theme-demo::messages.modals_filament_destroy_label'))
            ->icon('heroicon-o-trash')
            ->color(DaisyColor::ERROR->toFilamentColor())
            ->requiresConfirmation()
            ->modalHeading(__('theme-demo::messages.modals_filament_destroy_heading'))
            ->modalDescription(__('theme-demo::messages.modals_filament_destroy_description'))
            ->modalSubmitActionLabel(__('theme-demo::messages.modals_filament_destroy_submit'))
            ->action(fn () => $this->notifyRan());
    }

    /** Modal that collects input through a schema before the action runs. */
    public function formModalAction(): Action
    {
        return Action::make('formModal')
            ->label(__('theme-demo::messages.modals_filament_form_label'))
            ->icon('heroicon-o-pencil-square')
            ->schema([
                Forms\Components\TextInput::make('reason')
                    ->label(__('theme-demo::messages.modals_filament_form_reason'))
                    ->required(),
            ])
            ->action(function (array $data): void {
                Notification::make()
                    ->title(__('theme-demo::messages.modals_filament_form_confirmed', ['reason' => $data['reason']]))
                    ->success()
                    ->send();
            });
    }

    private function notifyRan(): void
    {
        Notification::make()
            ->title(__('theme-demo::messages.modals_filament_confirmed'))
            ->success()
            ->send();
    }

    public function render(): View
    {
        return view('theme-demo::livewire.filament-modal-gallery')->layout('layouts.admin');
    }
}
