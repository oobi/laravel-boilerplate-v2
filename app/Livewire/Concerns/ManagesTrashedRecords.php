<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Support\Theme\DaisyColor;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
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
    /** @var array{active?: int, trashed?: int} counted once per request (read when the view renders, after any action) */
    private array $recordCounts = [];

    protected function trashedRecordsModel(): string
    {
        return $this->getTable()->getModel();
    }

    public function activeRecordsCount(): int
    {
        return $this->recordCounts['active'] ??= $this->trashedRecordsModel()::query()->count();
    }

    public function trashedRecordsCount(): int
    {
        return $this->recordCounts['trashed'] ??= $this->trashedRecordsModel()::onlyTrashed()->count();
    }

    protected function emptyTrashQuery(): Builder
    {
        return $this->trashedRecordsModel()::onlyTrashed();
    }

    /** Whether one trashed record may be emptied; override to check each (e.g. its policy). */
    protected function mayEmpty(Model $record): bool
    {
        return true;
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
            ->color(DaisyColor::ERROR->toFilamentColor())
            ->requiresConfirmation()
            ->modalHeading(__('admin.empty_trash'))
            ->modalDescription(__('admin.empty_trash_confirm'))
            ->authorize(fn (): bool => is_null($this->emptyTrashPermission()) || Gate::allows($this->emptyTrashPermission()))
            ->action(function (): void {
                // Force-delete each record rather than a bulk query delete: a bulk
                // delete skips model events, so per-model deletion cleanup (e.g. a
                // User's stored profile photo) would never run — see GitHub #12.
                [$records, $kept] = $this->emptyTrashQuery()->get()->partition(fn (Model $record): bool => $this->mayEmpty($record));
                $count = $records->count();

                $records->each->forceDelete();

                $this->tableFilters[$this->trashedFilterName()]['value'] = '';

                Notification::make()
                    ->title(__('admin.empty_trash_success', ['count' => $count]))
                    // Any left behind (mayEmpty() said no) are explained, not silently skipped.
                    ->body($kept->isEmpty() ? null : trans_choice('admin.empty_trash_kept', $kept->count(), ['count' => $kept->count()]))
                    ->success()
                    ->send();
            });
    }
}
