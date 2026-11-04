<?php

declare(strict_types=1);

namespace Concise\ThemeDemo\Livewire\Tables;

use App\Enums\SystemPermission;
use App\Support\DateRange;
use App\Support\Theme\DaisyColor;
use Concise\ThemeDemo\Enums\DemoStatus;
use Concise\ThemeDemo\Support\DemoRow;
use Concise\ThemeDemo\Support\DemoRows;
use Concise\ThemeDemo\Support\WideDemoColumns;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Hidden;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\IconPosition;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\Layout\Grid;
use Filament\Tables\Columns\Layout\Panel;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;
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
 * "maximalist" keeps Filament's native row-expansion (a Grid + a collapsible
 * Panel, see maximalistColumns()). But ANY Layout\Component among the columns
 * makes Filament render every row through its generic layout renderer instead
 * of plain <table>/<tr>/<td>, unconditionally (confirmed at 1600px wide, not a
 * mobile fallback): a Grid lines up each row, but the header row is
 * always a "Sort by" dropdown, never real sortable <th> cells. So
 * "custom-header" drops Split/Panel entirely for the classic table with real
 * header cells instead; its "View" opens a modal with the extra detail as a
 * substitute for the expand. Two genuinely different Filament trade-offs,
 * not a bug in either. Neither is the way to make a table fit a phone: that's
 * ->stackedOnMobile(), on for every table (see .ai/rules/tables.md).
 *
 * "grouped" groups rows by status and shows each row's email under its
 * name, so rows are tall: the group headings and `ui-cells-top` (cells and
 * the row menu level with the first line) from filament-table.css.
 */
class FilamentTable extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    private const VARIANTS = ['empty', 'simple', 'maximalist', 'custom-header', 'wide', 'grouped'];

    /** Variants with the full DemoRows set + search/sort/filter/bulk/action-menu — everything but "empty", "simple" and "wide". */
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

    /**
     * Each status's badge colour as its <x-listbox> dot.
     *
     * @return array<string, string>
     */
    public function statusDots(): array
    {
        return collect(DemoStatus::cases())
            ->mapWithKeys(fn (DemoStatus $status): array => [$status->value => 'bg-'.DaisyColor::fromFilamentColor($status->getColor())->value])
            ->all();
    }

    /**
     * The Joined filter's shortcuts; any other value is days picked on its calendar.
     *
     * @return array<string, string>
     */
    public function joinedPresets(): array
    {
        return ['' => __('Any date'), 'last_30' => __('Last 30 days'), 'this_year' => __('This year')];
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
        $isWide = $this->variant === 'wide';
        $isGrouped = $this->variant === 'grouped';

        $table = $table
            ->records(function (?string $search, ?string $sortColumn, ?string $sortDirection, ?array $filters, int|string $page, int|string $recordsPerPage) use ($isFullFeatured, $isWide): LengthAwarePaginator {
                if ($this->variant === 'empty') {
                    return new LengthAwarePaginator(collect(), 0, (int) $recordsPerPage, (int) $page);
                }

                $rows = match (true) {
                    $this->variant === 'simple' => DemoRows::take(8)->keyBy('id'),
                    $isWide => DemoRows::take(30)->keyBy('id'),
                    // Custom data isn't sorted for grouping, so keep each status together.
                    $this->variant === 'grouped' => DemoRows::take(12)->sortBy(fn (DemoRow $row): int => array_search($row->status, DemoStatus::cases(), true))->keyBy('id'),
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

                    // A preset, or days picked with <x-date-picker> (DateRange reads them).
                    $joined = data_get($filters, 'joined.value');
                    $range = DateRange::parse($joined) ?? match ($joined) {
                        'last_30' => DateRange::parse(now()->subDays(29)->toDateString().'/'.now()->toDateString()),
                        'this_year' => DateRange::parse(now()->startOfYear()->toDateString().'/'.now()->endOfYear()->toDateString()),
                        default => null,
                    };

                    if ($range !== null) {
                        $rows = $rows->filter(fn (DemoRow $row): bool => $row->joinedAt >= $range->from && ($range->to === null || $row->joinedAt <= $range->to));
                    }

                    if (filled($sortColumn)) {
                        $rows = $rows->sortBy(
                            fn (DemoRow $row): string => match ($sortColumn) {
                                'joined_at' => $row->joinedAt,
                                default => $row->{$sortColumn} instanceof DemoStatus ? $row->{$sortColumn}->value : (string) $row->{$sortColumn},
                            },
                            SORT_REGULAR,
                            $sortDirection === 'desc',
                        );
                    }
                }

                $records = $rows->map(function (DemoRow $row) use ($isFullFeatured, $isWide): array {
                    $record = [
                        'id' => $row->id,
                        'name' => $row->name,
                        'email' => $row->email,
                        'status' => $row->status,
                        'joined_at' => $row->joinedAt,
                        'detail' => $row->detail,
                    ];

                    if ($isFullFeatured) {
                        $record += $this->extraColumnValues($row);
                    }

                    if ($isWide) {
                        $record += $this->wideColumnValues($row);
                    }

                    return $record;
                });

                return new LengthAwarePaginator(
                    $records->forPage((int) $page, (int) $recordsPerPage)->values(),
                    $records->count(),
                    (int) $recordsPerPage,
                    (int) $page,
                );
            })
            ->columns(match (true) {
                $isWide => $this->wideColumns(),
                $isGrouped => $this->groupedColumns(),
                $this->variant === 'maximalist' => $this->maximalistColumns(),
                default => $this->primaryColumns($isFullFeatured),
            })
            // Every table stacks its rows on a phone (AppServiceProvider); "wide" is the
            // genuinely wide data grid that opts out and keeps the sideways scroll.
            ->stackedOnMobile(! $isWide)
            ->emptyStateHeading(__('theme-demo::messages.tables_empty_message'))
            ->emptyStateDescription(__('theme-demo::messages.tables_empty_hint'))
            ->paginated([10, 25])
            // "wide" is tall too: its footer starts below the fold, with more than one page.
            ->defaultPaginationPageOption($isWide ? 25 : 10);

        if ($isGrouped) {
            return $table
                ->defaultGroup(Group::make('status')
                    ->getTitleFromRecordUsing(fn (array $record): string => $record['status']->getLabel())
                    ->titlePrefixedWithLabel(false))
                ->groupingSettingsHidden()
                ->extraAttributes(['class' => 'ui-cells-top'])
                ->recordActions([
                    ActionGroup::make([
                        Action::make('view')
                            ->label(__('View'))
                            ->icon('heroicon-o-eye')
                            ->action(fn (array $record) => Notification::make()->title(__('Demo only: nothing to view.'))->send()),
                    ]),
                ]);
        }

        if (! $isFullFeatured) {
            return $table;
        }

        return $table
            ->filters([
                SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options(DemoStatus::options())
                    ->modifyFormFieldUsing(fn ($field) => $field->live(debounce: '1ms')),

                // Set by <x-date-picker> in the custom header; applied in records().
                Filter::make('joined')
                    ->schema([Hidden::make('value')->default('')]),
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

    /** @return array<int, TextColumn> */
    private function primaryColumns(bool $isFullFeatured): array
    {
        return [
            // Full-featured: the avatar beside the name, not a column of its own further along.
            ...($isFullFeatured ? [
                ImageColumn::make('avatar_url')
                    // Named for a screen reader only: a visible "Avatar" heading over pictures is noise.
                    ->label(new HtmlString('<span class="sr-only">'.e(__('Avatar')).'</span>'))
                    ->circular()
                    ->imageSize(40)
                    // As narrow as the picture, so the name sits right beside it.
                    ->width('1%')
                    // Not on a phone: the stacked card leads with the name (filament-table.css).
                    ->visibleFrom('sm'),
            ] : []),

            $this->withVerifiedBadge(TextColumn::make('name')
                ->label(__('Name'))
                ->description(fn (array $record): string => $record['email'])
                // A long email breaks rather than widen the table on a tablet.
                ->wrap()
                ->extraAttributes(['class' => 'wrap-anywhere'])
                ->searchable()
                ->sortable($isFullFeatured), $isFullFeatured),

            TextColumn::make('status')
                ->label(__('Status'))
                ->status()
                ->sortable($isFullFeatured),

            TextColumn::make('joined_at')
                ->label(__('Joined'))
                ->date()
                ->sortable($isFullFeatured)
                ->visibleFrom($isFullFeatured ? 'lg' : null),

            ...($isFullFeatured ? $this->extraColumns() : []),
        ];
    }

    /**
     * A verified person's name gets a badge icon after it, with "(verified)" for
     * a screen reader, instead of a Verified column of its own. One inline span
     * around the name: a bare sr-only sibling would push the icon onto its own
     * line inside Filament's line-clamped text.
     */
    private function withVerifiedBadge(TextColumn $column, bool $condition = true): TextColumn
    {
        if (! $condition) {
            return $column;
        }

        return $column
            ->formatStateUsing(fn (string $state, array $record): string|HtmlString => $record['verified']
                ? new HtmlString('<span class="pe-1">'.e($state).'<span class="sr-only"> ('.e(__('verified')).')</span></span>')
                : $state)
            ->icon(fn (array $record): ?Heroicon => $record['verified'] ? Heroicon::CheckBadge : null)
            ->iconPosition(IconPosition::After)
            ->iconColor('info');
    }

    /**
     * The maximalist row: a Grid layout, not a Split. A Split sizes each column
     * to its own content, so nothing lines up from one row to the next; a Grid
     * gives every row the same tracks. Twelve on a wide table (the person, then
     * status, joined date and priority in fixed columns), two on a narrow one
     * (the person across the top, status and date side by side, then the
     * priority select across the row). The switch goes by the table's own width
     * (`@4xl`, a container query), not the screen's, so it holds beside the
     * pinned sidebar. Every value names itself, since a layout table has no
     * column headings: the priority options say "priority", the date has
     * "Joined" above it and the verified badge has a screen-reader label. The detail
     * and the featured toggle (with its word beside it) open in a collapsible
     * panel.
     *
     * @return list<Grid|Panel>
     */
    private function maximalistColumns(): array
    {
        $demoOnly = fn (mixed $state) => Notification::make()->title(__('Demo only: nothing saved.'))->send();

        return [
            Grid::make(['default' => 2, '@4xl' => 12])->extraAttributes(['class' => 'items-center'])->schema([
                Split::make([
                    ImageColumn::make('avatar_url')
                        ->label(__('Avatar'))
                        ->circular()
                        ->imageSize(40)
                        ->grow(false),

                    Stack::make([
                        $this->withVerifiedBadge(TextColumn::make('name')
                            ->label(__('Name'))
                            ->weight(FontWeight::Medium)
                            ->searchable()
                            ->sortable()),

                        TextColumn::make('email')
                            ->label(__('Email'))
                            ->color('gray')
                            ->size(TextSize::Small),
                    ]),
                ])->columnSpan(['default' => 2, '@4xl' => 5]),

                TextColumn::make('status')
                    ->label(__('Status'))
                    ->status()
                    ->sortable()
                    ->columnSpan(['default' => 1, '@4xl' => 2]),

                TextColumn::make('joined_at')
                    ->label(__('Joined'))
                    ->date()
                    ->description(__('Joined'), position: 'above')
                    ->color('gray')
                    ->sortable()
                    ->columnSpan(['default' => 1, '@4xl' => 2]),

                SelectColumn::make('priority')
                    ->label(__('Priority'))
                    ->options(['low' => __('Low priority'), 'medium' => __('Medium priority'), 'high' => __('High priority')])
                    ->selectablePlaceholder(false)
                    ->updateStateUsing($demoOnly)
                    ->columnSpan(['default' => 2, '@4xl' => 3]),
            ]),

            Panel::make([
                Split::make([
                    TextColumn::make('detail')
                        ->label(__('Detail')),

                    Split::make([
                        ToggleColumn::make('featured')
                            ->label(__('Featured'))
                            ->updateStateUsing($demoOnly)
                            ->grow(false),

                        TextColumn::make('featured_label')
                            ->state(__('Featured'))
                            ->size(TextSize::Small),
                    ])->grow(false),
                ])->from('md'),
            ])->collapsible(),
        ];
    }

    /** Editable column types from the Filament docs, beyond TextColumn. Demo-only values, see extraColumnValues(). */
    private function extraColumns(): array
    {
        $demoOnly = fn (mixed $state) => Notification::make()->title(__('Demo only: nothing saved.'))->send();

        return [
            SelectColumn::make('priority')
                ->label(__('Priority'))
                ->options(['low' => __('Low'), 'medium' => __('Medium'), 'high' => __('High')])
                ->selectablePlaceholder(false)
                ->updateStateUsing($demoOnly),

            ToggleColumn::make('featured')
                ->label(__('Featured'))
                ->updateStateUsing($demoOnly)
                ->visibleFrom('lg'),
        ];
    }

    /** @return array<string, mixed> */
    private function extraColumnValues(DemoRow $row): array
    {
        return [
            'avatar_url' => 'https://ui-avatars.com/api/?name='.urlencode($row->name).'&background=random',
            'verified' => $row->status === DemoStatus::ACTIVE,
            'priority' => ['low', 'medium', 'high'][$row->id % 3],
            'featured' => $row->id % 4 === 0,
        ];
    }

    /** @return list<TextColumn> */
    private function wideColumns(): array
    {
        $columns = [
            TextColumn::make('name')->label(__('Name'))->searchable(),
            TextColumn::make('status')->label(__('Status'))->status(),
            TextColumn::make('joined_at')->label(__('Joined'))->date(),
        ];

        foreach (WideDemoColumns::keys() as $key) {
            $columns[] = TextColumn::make($key)->label(WideDemoColumns::label($key));
        }

        return $columns;
    }

    /** @return list<TextColumn> */
    private function groupedColumns(): array
    {
        return [
            TextColumn::make('name')
                ->label(__('Name'))
                ->weight('medium')
                ->description(fn (array $record): string => $record['email'])
                ->wrap(),
            TextColumn::make('joined_at')->label(__('Joined'))->date(),
        ];
    }

    /** @return array<string, mixed> */
    private function wideColumnValues(DemoRow $row): array
    {
        return WideDemoColumns::valuesFor($row);
    }

    public function render(): View
    {
        return view('theme-demo::livewire.tables.filament')->layout('layouts.admin');
    }
}
