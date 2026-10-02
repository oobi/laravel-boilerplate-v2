<?php

declare(strict_types=1);

namespace Concise\ThemeDemo\Livewire;

use App\Enums\SystemPermission;
use App\Support\Theme\DaisyColor;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Support\Enums\IconPosition;
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

    /**
     * Editing a record: things to do with it (Duplicate, Archive, Delete) in a
     * More actions menu on the left, apart from Cancel and Save on the right
     * (ui-modal-footer-split). Each item closes the form first.
     */
    public function recordModalAction(): Action
    {
        return Action::make('recordModal')
            ->label(__('theme-demo::messages.modals_filament_record_label'))
            ->icon('heroicon-o-pencil-square')
            ->modalHeading(__('theme-demo::messages.modals_filament_record_heading'))
            ->extraModalWindowAttributes(['class' => 'ui-modal-footer-split'])
            ->fillForm(['title' => __('theme-demo::messages.modals_filament_record_title_value')])
            ->schema([
                Forms\Components\TextInput::make('title')
                    ->label(__('theme-demo::messages.modals_filament_record_title'))
                    ->required(),
            ])
            ->modalSubmitActionLabel(__('theme-demo::messages.modals_filament_record_save'))
            ->extraModalFooterActions([
                ActionGroup::make([
                    Action::make('duplicateRecord')
                        ->label(__('theme-demo::messages.modals_filament_record_duplicate'))
                        ->icon('heroicon-o-document-duplicate')
                        ->action(fn () => $this->notifyRan())
                        ->cancelParentActions(),
                    Action::make('archiveRecord')
                        ->label(__('theme-demo::messages.modals_filament_record_archive'))
                        ->icon('heroicon-o-archive-box')
                        ->color(DaisyColor::WARNING->toFilamentColor())
                        ->requiresConfirmation()
                        ->action(fn () => $this->notifyRan())
                        ->cancelParentActions(),
                    Action::make('deleteRecord')
                        ->label(__('theme-demo::messages.modals_filament_record_delete'))
                        ->icon('heroicon-o-trash')
                        ->color(DaisyColor::ERROR->toFilamentColor())
                        ->requiresConfirmation()
                        ->action(fn () => $this->notifyRan())
                        ->cancelParentActions(),
                ])
                    ->label(__('theme-demo::messages.modals_filament_record_more'))
                    ->icon('heroicon-m-chevron-down')
                    ->iconPosition(IconPosition::After)
                    ->color(DaisyColor::NEUTRAL->toFilamentColor())
                    ->button()
                    ->outlined(),
            ])
            ->action(fn () => $this->notifyRan());
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
