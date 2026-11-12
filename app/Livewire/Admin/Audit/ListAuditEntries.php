<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Audit;

use App\Enums\SystemPermission;
use App\Models\AuditEntry;
use App\Models\User;
use App\Support\Audit\Audit;
use App\Support\Audit\AuditDisplay;
use App\Support\Audit\AuditEntryFilters;
use App\Support\Audit\AuditView;
use App\Support\Audit\SessionTrail;
use App\Support\DateRange;
use App\Support\DisplayTimezone;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Hidden;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Json;
use Livewire\Attributes\Url;
use Livewire\Component;

/** The audit log: read only, newest first (one session oldest first), filtered with the toolbar in the view. */
class ListAuditEntries extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable {
        InteractsWithTable::handleTableFilterUpdates as filamentHandleTableFilterUpdates;
    }

    /**
     * One person's activity: the Person filter, kept in the URL so a user's page
     * can link here and a reload keeps it. A string: the URL can hold anything.
     */
    #[Url(as: 'user')]
    public ?string $userId = null;

    /** One browser session's entries, from an entry's page. */
    #[Url(as: 'session')]
    public ?string $sessionTrail = null;

    /** The quick view (an AuditView value). A string: the URL can hold anything. */
    #[Url(as: 'view', except: 'all')]
    public string $activeView = 'all';

    /** @var array<string, Collection<int, string>> */
    private array $distinctValues = [];

    /**
     * People looked up this request, by id (false: no such user), so the picker
     * and the filter chip don't each query for the same one.
     *
     * @var array<int, User|false>
     */
    private array $people = [];

    public function mount(): void
    {
        Gate::authorize(SystemPermission::VIEW_AUDIT_LOG->value);

        // Only a real id is kept: anything else, even text Livewire can't save, means everyone.
        $userId = AuditEntryFilters::userId($this->userId);
        $this->userId = $userId === null ? null : (string) $userId;
        $this->sessionTrail = SessionTrail::normalize($this->sessionTrail);
        $this->activeView = $this->quickView()->value;

        // Set here, not as the filter's default: resetting the filters must clear it.
        if ($this->userId !== null) {
            $this->tableFilters['person']['value'] = $this->userId;
        }
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => tap(
                AuditEntry::query()->with(['subject', 'causer']),
                function (Builder $query): void {
                    AuditEntryFilters::forSession($query, $this->sessionTrail);
                    $this->quickView()->apply($query);
                },
            ))
            // One session reads in the order it happened; everything else, newest first.
            ->defaultSort(fn (Builder $query): Builder => $this->isFilteredToSession()
                ? $query->orderBy('created_at')->orderBy('id')
                : $query->orderByDesc('created_at')->orderByDesc('id'))
            // The whole phrase, as the export searches: the two must hold the same rows.
            ->splitSearchTerms(false)
            ->columns([
                // One sentence per entry; the day headings come from the grouping below.
                Tables\Columns\ViewColumn::make('description')
                    ->label(__('audit.what'))
                    ->view('filament.tables.columns.audit-feed-row')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        AuditEntryFilters::search($query, $search);

                        return $query;
                    }),
            ])
            ->defaultGroup(
                Group::make('created_at')
                    ->collapsible(false)
                    ->titlePrefixedWithLabel(false)
                    ->getKeyFromRecordUsing(fn (AuditEntry $record): string => DisplayTimezone::of($record->created_at)->toDateString())
                    ->getTitleFromRecordUsing(fn (AuditEntry $record): string => $this->dayHeading($record))
                    // The sort below already orders by time: grouping adds nothing.
                    ->orderQueryUsing(fn (Builder $query): Builder => $query),
            )
            ->groupingSettingsHidden()
            ->columnManager(false)
            ->extraAttributes(['class' => 'ui-cells-top ui-feed-rows'])
            ->filters([
                SelectFilter::make('event')
                    ->label(__('audit.event'))
                    ->options(fn (): array => $this->eventOptions())
                    ->modifyFormFieldUsing(fn ($field) => $field->live(debounce: '1ms'))
                    ->query(fn (Builder $query, array $data) => AuditEntryFilters::equals($query, 'event', $data['value'] ?? null)),

                SelectFilter::make('actor_type')
                    ->label(__('audit.actor_type'))
                    ->options(fn (): array => $this->actorTypeOptions())
                    ->modifyFormFieldUsing(fn ($field) => $field->live(debounce: '1ms'))
                    ->query(fn (Builder $query, array $data) => AuditEntryFilters::equals($query, 'actor_type', $data['value'] ?? null)),

                // Set by the Person <x-listbox> in the toolbar, or a user's page through ?user=.
                Filter::make('person')
                    ->schema([Hidden::make('value')->default('')])
                    ->query(fn (Builder $query, array $data) => AuditEntryFilters::forUser($query, $data['value'] ?? null))
                    ->indicateUsing(fn (array $data): ?string => AuditEntryFilters::userId($data['value'] ?? null) === null ? null : __('audit.person').': '.$this->personLabel()),

                // Set by <x-date-picker> in the toolbar.
                Filter::make('date')
                    ->schema([Hidden::make('value')->default('')])
                    ->query(fn (Builder $query, array $data) => AuditEntryFilters::date($query, $data['value'] ?? null))
                    ->indicateUsing(fn (array $data): ?string => DateRange::parse($data['value'] ?? null) === null ? null : __('audit.date').': '.str_replace('/', ' to ', (string) $data['value'])),
            ])
            ->deferFilters(false)
            ->recordActions([
                ActionGroup::make([
                    Action::make('view')
                        ->label(__('admin.view'))
                        ->icon('heroicon-o-eye')
                        ->url(fn (AuditEntry $record): string => route('audit.show', $record)),

                    // What else happened in the same sitting; pointless once the list is one session.
                    Action::make('session')
                        ->label(__('audit.view_session'))
                        ->icon('heroicon-o-queue-list')
                        ->url(fn (AuditEntry $record): ?string => $record->session_trail === null ? null : route('audit.index', ['session' => $record->session_trail]))
                        ->visible(fn (AuditEntry $record): bool => $record->session_trail !== null && ! $this->isFilteredToSession()),
                ]),
            ]);
    }

    /** The quick view shown; anything the URL holds that isn't one means All. */
    public function quickView(): AuditView
    {
        return AuditView::tryFrom($this->activeView) ?? AuditView::ALL;
    }

    /**
     * The quick views as tabs, each with how many entries it holds under the
     * search and filters in use, so a tab's count matches the rows it shows.
     * One grouped count; Filament already counts the rows for its pagination.
     * A view whose categories the log has never held (Email, until email
     * logging exists) is left out, unless it's the one open.
     *
     * @return list<array{view: AuditView, label: string, count: int, active: bool}>
     */
    public function quickViews(): array
    {
        $logged = $this->distinctValues('log_name');
        $query = AuditEntry::query();
        AuditEntryFilters::apply($query, [...$this->exportFilters(), 'view' => null]);

        $counts = AuditView::counts($query->toBase()
            ->reorder()
            ->selectRaw('log_name, count(*) as aggregate')
            ->groupBy('log_name')
            ->pluck('aggregate', 'log_name')
            ->map(fn (mixed $count): int => (int) $count));

        return collect(AuditView::cases())
            ->filter(fn (AuditView $view): bool => $view === AuditView::ALL
                || $view === $this->quickView()
                || $logged->intersect($view->categories())->isNotEmpty())
            ->map(fn (AuditView $view): array => [
                'view' => $view,
                'label' => $view->label(),
                'count' => $counts[$view->value],
                'active' => $view === $this->quickView(),
            ])
            ->values()
            ->all();
    }

    public function showView(string $view): void
    {
        $this->activeView = (AuditView::tryFrom($view) ?? AuditView::ALL)->value;
        $this->resetPage($this->getTablePaginationPageName());
        $this->flushCachedTableRecords();
    }

    /** @return array<string, string> */
    public function eventOptions(): array
    {
        return $this->byLabel($this->distinctValues('event')->mapWithKeys(fn (string $event): array => [$event => Audit::describe($event)]));
    }

    /** @return array<string, string> */
    public function actorTypeOptions(): array
    {
        return $this->byLabel($this->distinctValues('actor_type')->mapWithKeys(fn (string $type): array => [$type => AuditDisplay::actorLabel($type)]));
    }

    /**
     * People matching what was typed into the Person filter, a deleted one too:
     * their entries outlive them. Called from the browser, so it checks access itself.
     *
     * @return list<array{value: string, label: string}>
     */
    #[Json]
    public function searchPeople(string $search): array
    {
        // abort, not Gate::authorize(): a #[Json] call reports only an HTTP status, and
        // an AuthorizationException would read as a 500.
        abort_unless(Gate::allows(SystemPermission::VIEW_AUDIT_LOG->value), 403);

        $search = mb_substr(trim($search), 0, 100);

        if ($search === '') {
            return [];
        }

        return User::withTrashed()
            ->whereAnyContains(['first_name', 'last_name', 'email'], $search)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->orderBy('id')
            ->limit(20)
            ->get()
            ->map(fn (User $user): array => ['value' => (string) $user->getKey(), 'label' => $this->describePerson($user)])
            ->all();
    }

    /** The person the list is narrowed to, as the Person filter shows them. */
    public function personLabel(): ?string
    {
        if (! $this->isFilteredToUser()) {
            return null;
        }

        $user = $this->filteredUser();

        return $user === null ? __('audit.deleted_person') : $this->describePerson($user);
    }

    /** Keeps ?user= in step with the Person filter, however it changed (picked, removed, reset). */
    protected function handleTableFilterUpdates(): void
    {
        $this->filamentHandleTableFilterUpdates();

        $userId = AuditEntryFilters::userId(data_get($this->tableFilters, 'person.value'));
        $this->userId = $userId === null ? null : (string) $userId;
    }

    public function isFilteredToUser(): bool
    {
        return AuditEntryFilters::userId(data_get($this->tableFilters, 'person.value')) !== null;
    }

    public function isFilteredToSession(): bool
    {
        return SessionTrail::normalize($this->sessionTrail) !== null;
    }

    /** The person the list is narrowed to. A permanently deleted one still has entries. */
    public function filteredUser(): ?User
    {
        $id = AuditEntryFilters::userId(data_get($this->tableFilters, 'person.value'));

        if ($id === null) {
            return null;
        }

        return ($this->people[$id] ??= User::withTrashed()->find($id) ?? false) ?: null;
    }

    /**
     * What the export should hold: the same filters as the screen.
     *
     * @return array<string, string|null>
     */
    public function exportFilters(): array
    {
        return [
            'view' => $this->quickView() === AuditView::ALL ? null : $this->quickView()->value,
            'event' => data_get($this->tableFilters, 'event.value'),
            'actor_type' => data_get($this->tableFilters, 'actor_type.value'),
            'date' => data_get($this->tableFilters, 'date.value'),
            'search' => $this->tableSearch,
            'user' => $this->isFilteredToUser() ? (string) data_get($this->tableFilters, 'person.value') : null,
            'session' => $this->isFilteredToSession() ? $this->sessionTrail : null,
        ];
    }

    public function canExport(): bool
    {
        return Gate::allows(SystemPermission::EXPORT_AUDIT_LOG->value);
    }

    public function render(): View
    {
        return view('livewire.admin.audit.list-audit-entries');
    }

    /** The day an entry falls on in the display zone: "Today, Thursday 12 November", "Yesterday, …", then the date. */
    private function dayHeading(AuditEntry $record): string
    {
        $day = DisplayTimezone::of($record->created_at);
        $today = DisplayTimezone::now();
        $date = $day->translatedFormat($day->year === $today->year ? 'l j F' : 'l j F Y');

        return match (true) {
            $day->isSameDay($today) => __('audit.today_heading', ['date' => $date]),
            $day->isSameDay($today->subDay()) => __('audit.yesterday_heading', ['date' => $date]),
            default => $date,
        };
    }

    private function describePerson(User $user): string
    {
        return __($user->trashed() ? 'audit.person_option_deleted' : 'audit.person_option', ['name' => $user->name, 'email' => $user->email]);
    }

    /**
     * In the order they read, ignoring case and with "2fa" before "10fa".
     *
     * @param  Collection<string, string>  $options
     * @return array<string, string>
     */
    private function byLabel(Collection $options): array
    {
        return $options->sort(SORT_NATURAL | SORT_FLAG_CASE)->all();
    }

    /**
     * What's actually in the log, so a project's own categories and events appear
     * by themselves. Read by the filter and the view on each render, so held once,
     * and cached because scanning a long log is most of the cost of a click. A new
     * kind of entry reaches the dropdowns within the five minutes.
     *
     * @return Collection<int, string>
     */
    private function distinctValues(string $column): Collection
    {
        return $this->distinctValues[$column] ??= collect(Cache::remember(
            "audit.filter_options.{$column}",
            now()->addMinutes(5),
            fn (): array => AuditEntry::query()->whereNotNull($column)->distinct()->orderBy($column)->pluck($column)->all(),
        ));
    }
}
