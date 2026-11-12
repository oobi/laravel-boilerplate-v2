<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Audit;

use App\Enums\SystemPermission;
use App\Models\AuditEntry;
use App\Models\User;
use App\Support\Audit\Audit;
use App\Support\Audit\AuditDisplay;
use App\Support\Audit\AuditEntryFilters;
use App\Support\Audit\SessionTrail;
use App\Support\DateRange;
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
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;
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
                fn (Builder $query) => AuditEntryFilters::forSession($query, $this->sessionTrail),
            ))
            // One session reads in the order it happened; everything else, newest first.
            ->defaultSort(fn (Builder $query): Builder => $this->isFilteredToSession()
                ? $query->orderBy('created_at')->orderBy('id')
                : $query->orderByDesc('created_at')->orderByDesc('id'))
            ->extraAttributes(['class' => 'ui-cells-top'])
            // The whole phrase, as the export searches: the two must hold the same rows.
            ->splitSearchTerms(false)
            ->columns([
                Tables\Columns\ViewColumn::make('description')
                    ->label(__('audit.what'))
                    ->view('filament.tables.columns.audit-entry-column')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        AuditEntryFilters::search($query, $search);

                        return $query;
                    }),

                Tables\Columns\ViewColumn::make('actor_name')
                    ->label(__('audit.actor'))
                    ->view('filament.tables.columns.audit-actor-column'),

                Tables\Columns\ViewColumn::make('subject_id')
                    ->label(__('audit.subject'))
                    ->view('filament.tables.columns.audit-subject-column'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('audit.when'))
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('ip_address')
                    ->label(__('audit.ip_address'))
                    // A dash, still read out as what it means, as in the Users list.
                    ->placeholder(new HtmlString('<span aria-hidden="true">–</span><span class="sr-only">'.e(__('audit.none')).'</span>'))
                    ->visibleFrom('lg'),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->label(__('audit.category'))
                    ->options(fn (): array => $this->categoryOptions())
                    ->modifyFormFieldUsing(fn ($field) => $field->live(debounce: '1ms'))
                    ->query(fn (Builder $query, array $data) => AuditEntryFilters::equals($query, 'log_name', $data['value'] ?? null)),

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

    /** @return array<string, string> */
    public function categoryOptions(): array
    {
        return $this->byLabel($this->distinctValues('log_name')->mapWithKeys(fn (string $category): array => [$category => AuditDisplay::categoryLabel($category)]));
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
            'category' => data_get($this->tableFilters, 'category.value'),
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
