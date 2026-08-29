<?php

declare(strict_types=1);

namespace Concise\ThemeDemo\Livewire\Tables;

use App\Enums\SystemPermission;
use Concise\ThemeDemo\Enums\DemoStatus;
use Concise\ThemeDemo\Support\DemoRow;
use Concise\ThemeDemo\Support\DemoRows;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Columns\Layout\Panel;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * Filament equivalent of the daisyUI table demos — same tiers (empty/simple/
 * maximalist), same DemoRows data, but declarative: search/sort/filter/bulk-
 * actions/pagination all come from ->records() + column config instead of
 * hand-rolled Livewire state. `$variant` picks which tier; "custom-header"
 * has the exact same table config as "maximalist" — it only differs in the
 * view, swapping Filament's own header for OUR <x-table-header>, the same
 * way every *real* admin table in this app does it (see ListUsers).
 *
 * "maximalist" keeps Filament's native row-expansion (Split + a collapsible
 * Panel column) — but ANY Layout\Component among the columns makes Filament
 * render every row through its generic layout renderer instead of plain
 * <table>/<tr>/<td>, unconditionally (confirmed at 1600px wide, not a mobile
 * fallback): Split still lines up each row correctly, but the header row is
 * always a "Sort by" dropdown, never real sortable <th> cells. So
 * "custom-header" drops Split/Panel entirely for the classic table with real
 * header cells instead; its "View" opens a modal with the extra detail as a
 * substitute for the expand. Two genuinely different Filament trade-offs,
 * not a bug in either.
 */
class FilamentTable extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    private const VARIANTS = ['empty', 'simple', 'maximalist', 'custom-header'];

    /** Variants with the full DemoRows set + search/sort/filter/bulk/action-menu — everything but "empty" and "simple". */
    private const FULL_FEATURED_VARIANTS = ['maximalist', 'custom-header'];

    public string $variant;

    /** Cosmetic only — toggles between DemoRows::all() and DemoRows::trashed(), no real soft-delete. */
    public string $trashedFilter = '';

    public function mount(string $variant): void
    {
        Gate::authorize(SystemPermission::ACCESS_ADMIN_PANEL->value);

        abort_unless(in_array($variant, self::VARIANTS, true), 404);

        $this->variant = $variant;
    }

    /** @return array<string, string> */
    public function statusOptions(): array
    {
        return DemoStatus::options();
    }

    public function activeRecordsCount(): int
    {
        return DemoRows::all()->count();
    }

    public function trashedRecordsCount(): int
    {
        return DemoRows::trashed()->count();
    }

    public function emptyTrashAction(): Action
    {
        return Action::make('emptyTrash')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(__('Empty trash'))
            ->modalDescription(__('This demo does not really delete anything.'))
            ->action(function (): void {
                $this->trashedFilter = '';

                Notification::make()
                    ->title(__('Trash emptied (demo only).'))
                    ->success()
                    ->send();
            });
    }

    public function table(Table $table): Table
    {
        $isFullFeatured = in_array($this->variant, self::FULL_FEATURED_VARIANTS, true);

        $table = $table
            ->records(function (?string $search, ?string $sortColumn, ?string $sortDirection, ?array $filters, int|string $page, int|string $recordsPerPage) use ($isFullFeatured): LengthAwarePaginator {
                if ($this->variant === 'empty') {
                    return new LengthAwarePaginator(collect(), 0, (int) $recordsPerPage, (int) $page);
                }

                $rows = match (true) {
                    $this->variant === 'simple' => DemoRows::take(8)->keyBy('id'),
                    $this->variant === 'custom-header' && $this->trashedFilter === '0' => DemoRows::trashed()->keyBy('id'),
                    default => DemoRows::all()->keyBy('id'),
                };

                if (filled($search)) {
                    $rows = $rows->filter(fn (DemoRow $row): bool => str_contains(mb_strtolower($row->name), mb_strtolower($search)));
                }

                if ($isFullFeatured) {
                    if (filled($status = data_get($filters, 'status.value'))) {
                        $rows = $rows->filter(fn (DemoRow $row): bool => $row->status->value === $status);
                    }

                    if (filled($sortColumn)) {
                        $rows = $rows->sortBy(
                            fn (DemoRow $row): string => $row->{$sortColumn} instanceof DemoStatus ? $row->{$sortColumn}->value : (string) $row->{$sortColumn},
                            SORT_REGULAR,
                            $sortDirection === 'desc',
                        );
                    }
                }

                $records = $rows->map(fn (DemoRow $row): array => [
                    'id' => $row->id,
                    'name' => $row->name,
                    'email' => $row->email,
                    'status' => $row->status,
                    'joined_at' => $row->joinedAt,
                    'detail' => $row->detail,
                ]);

                return new LengthAwarePaginator(
                    $records->forPage((int) $page, (int) $recordsPerPage)->values(),
                    $records->count(),
                    (int) $recordsPerPage,
                    (int) $page,
                );
            })
            ->columns([
                Split::make([
                    TextColumn::make('name')
                        ->label(__('Name'))
                        ->description(fn (array $record): string => $record['email'])
                        ->searchable()
                        ->sortable($isFullFeatured),

                    TextColumn::make('status')
                        ->label(__('Status'))
                        ->badge()
                        ->sortable($isFullFeatured),

                    TextColumn::make('joined_at')
                        ->label(__('Joined'))
                        ->date()
                        ->sortable($isFullFeatured),
                ])->from('lg'),

                // A Layout\Component (Split here, Panel below) among the columns makes
                // Filament render every row through its own generic layout renderer
                // instead of plain <table>/<tr>/<td> — confirmed at 1600px wide, so it's
                // not a responsive/mobile fallback. Split still lays out each row as one
                // aligned line (verified), but the header row is always replaced by a
                // "Sort by" dropdown, no real sortable <th> cells — kept to "maximalist"
                // only; "custom-header" drops both Split and Panel for the classic table
                // with real header cells instead (see FULL_FEATURED_VARIANTS docblock).
                ...($this->variant === 'maximalist' ? [
                    Panel::make([
                        TextColumn::make('detail')
                            ->label(__('Detail'))
                            ->columnSpanFull(),
                    ])->collapsible(),
                ] : []),
            ])
            ->emptyStateHeading(__('theme-demo::messages.tables_empty_message'))
            ->emptyStateDescription(__('theme-demo::messages.tables_empty_hint'))
            ->paginated([10, 25]);

        if (! $isFullFeatured) {
            return $table;
        }

        return $table
            ->filters([
                SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options(DemoStatus::options())
                    ->modifyFormFieldUsing(fn ($field) => $field->live(debounce: '1ms')),
            ])
            ->deferFilters(false)
            ->recordActions([
                ActionGroup::make([
                    Action::make('view')
                        ->label(__('View'))
                        ->icon('heroicon-o-eye')
                        ->modalHeading(fn (array $record): string => $record['name'])
                        ->modalDescription(fn (array $record): string => $record['detail'])
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel(__('Close'))
                        ->hidden(fn (): bool => $this->trashedFilter === '0'),

                    Action::make('edit')
                        ->label(__('Edit'))
                        ->icon('heroicon-o-pencil-square')
                        ->action(fn () => Notification::make()->title(__('Demo only — nothing to edit.'))->send())
                        ->hidden(fn (): bool => $this->trashedFilter === '0'),

                    Action::make('archive')
                        ->label(__('Archive'))
                        ->icon('heroicon-o-archive-box')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->action(fn (array $record) => Notification::make()->title(__('Archived :name.', ['name' => $record['name']]))->success()->send())
                        ->hidden(fn (): bool => $this->trashedFilter === '0'),

                    Action::make('restore')
                        ->label(__('Restore'))
                        ->icon('heroicon-o-arrow-uturn-left')
                        ->color('success')
                        ->action(fn (array $record) => Notification::make()->title(__('Restored :name (demo only).', ['name' => $record['name']]))->success()->send())
                        ->visible(fn (): bool => $this->trashedFilter === '0'),

                    Action::make('forceDelete')
                        ->label(__('Delete permanently'))
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(fn (array $record) => Notification::make()->title(__('Deleted :name (demo only).', ['name' => $record['name']]))->success()->send())
                        ->visible(fn (): bool => $this->trashedFilter === '0'),
                ]),
            ])
            ->toolbarActions([
                BulkAction::make('archive')
                    ->label(__('Archive selected'))
                    ->icon('heroicon-o-archive-box')
                    ->color('warning')
                    ->hidden(fn (): bool => $this->trashedFilter === '0')
                    ->action(function (Collection $records): void {
                        Notification::make()
                            ->title(__('Archived :count demo row(s).', ['count' => $records->count()]))
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public function render(): View
    {
        return view('theme-demo::livewire.tables.filament')->layout('layouts.admin');
    }
}
