<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

/**
 * Adds discoverable active/trashed record counts and a one-click "empty
 * trash" Filament action to a Livewire component using Filament's
 * InteractsWithTable trait. The model is auto-derived from the table's
 * query, so adopting components need no per-model wiring by default.
 *
 * Override emptyTrashQuery()/emptyTrashPermission() to customise which
 * records are force-deleted and what authorisation is required.
 */
trait ManagesTrashedRecords
{
    protected function trashedRecordsModel(): string
    {
        return $this->getTable()->getModel();
    }

    public function activeRecordsCount(): int
    {
        return $this->trashedRecordsModel()::query()->count();
    }

    public function trashedRecordsCount(): int
    {
        return $this->trashedRecordsModel()::onlyTrashed()->count();
    }

    protected function emptyTrashQuery(): Builder
    {
        return $this->trashedRecordsModel()::onlyTrashed();
    }

    /** Return null for no extra permission check beyond the component's own mount() gate. */
    protected function emptyTrashPermission(): ?string
    {
        return null;
    }

    protected function trashedFilterName(): string
    {
        return 'trashed';
    }

    public function emptyTrashAction(): Action
    {
        return Action::make('emptyTrash')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(__('admin.empty_trash'))
            ->modalDescription(__('admin.empty_trash_confirm'))
            ->authorize(fn (): bool => is_null($this->emptyTrashPermission()) || Gate::allows($this->emptyTrashPermission()))
            ->action(function (): void {
                $count = $this->emptyTrashQuery()->count();

                $this->emptyTrashQuery()->forceDelete();

                $this->tableFilters[$this->trashedFilterName()]['value'] = '';

                Notification::make()
                    ->title(__('admin.empty_trash_success', ['count' => $count]))
                    ->success()
                    ->send();
            });
    }
}
