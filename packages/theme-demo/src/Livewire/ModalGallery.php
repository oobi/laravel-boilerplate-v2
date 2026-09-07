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
 * Design-system modal gallery — the reusable native-<dialog> shell (<x-modal> /
 * <x-confirm-modal>), its flavours, a modal that collects input, the four
 * semantic buttons, and the "sudo" password prompt (<x-confirm-password-modal>
 * + the ConfirmsPassword trait).
 */
class ModalGallery extends Component
{
    use ConfirmsPassword;

    public bool $showDelete = false;

    public bool $showImpersonate = false;

    public bool $showInfo = false;

    public bool $showSuccess = false;

    public bool $showArchive = false;

    public string $reason = '';

    public function mount(): void
    {
        Gate::authorize(SystemPermission::ACCESS_ADMIN_PANEL->value);
    }

    public function deleteRecord(): void
    {
        $this->notify(__('theme-demo::messages.modals_daisy_delete_done'));
    }

    public function impersonate(): void
    {
        $this->notify(__('theme-demo::messages.modals_daisy_impersonate_done'));
    }

    public function acknowledge(): void
    {
        $this->notify(__('theme-demo::messages.modals_daisy_acknowledged'));
    }

    public function archive(): void
    {
        $this->notify(__('theme-demo::messages.modals_daisy_archive_done', ['reason' => $this->reason]));

        $this->reset('reason');
        $this->showArchive = false;
    }

    /** Open the sudo prompt for one of the demo variants ('default' | 'custom'). */
    public function confirmDemo(string $variant): void
    {
        $this->startConfirmingPassword($variant);
    }

    public function render(): View
    {
        return view('theme-demo::livewire.modal-gallery')->layout('layouts.admin');
    }

    protected function dispatchConfirmedAction(string $action, array $arguments): void
    {
        match ($action) {
            'default', 'custom' => $this->notify(__('theme-demo::messages.modals_daisy_confirmed')),
            default => abort(403),
        };
    }

    private function notify(string $title): void
    {
        Notification::make()->title($title)->success()->send();
    }
}
