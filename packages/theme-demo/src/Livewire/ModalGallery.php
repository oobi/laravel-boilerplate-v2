<?php

declare(strict_types=1);

namespace Concise\ThemeDemo\Livewire;

use App\Enums\SystemPermission;
use App\Livewire\Concerns\ConfirmsPassword;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * DaisyUI modal gallery — demonstrates the app's one hand-rolled daisyUI modal,
 * the "sudo" password prompt (<x-confirm-password-modal> + the ConfirmsPassword
 * trait). Both trigger buttons open the same modal; the "custom copy" one shows
 * the title/description prop overrides.
 */
class ModalGallery extends Component
{
    use ConfirmsPassword;

    public function mount(): void
    {
        Gate::authorize(SystemPermission::ACCESS_ADMIN_PANEL->value);
    }

    /** Open the sudo prompt for one of the demo variants ('default' | 'custom'). */
    public function confirmDemo(string $variant): void
    {
        $this->startConfirmingPassword($variant);
    }

    protected function dispatchConfirmedAction(string $action, array $arguments): void
    {
        match ($action) {
            'default', 'custom' => Notification::make()
                ->title(__('theme-demo::messages.modals_daisy_confirmed'))
                ->success()
                ->send(),
            default => abort(403),
        };
    }

    public function render(): View
    {
        return view('theme-demo::livewire.modal-gallery')->layout('layouts.admin');
    }
}
