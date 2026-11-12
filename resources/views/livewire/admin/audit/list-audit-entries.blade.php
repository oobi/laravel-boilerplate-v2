<div>
    @section('page-title', __('admin.audit'))

    <x-page-header :title="__('admin.audit')" :description="__('audit.description')" />

    @if ($this->isFilteredToSession())
        <x-banner role="status" class="mb-6">
            {{ __('audit.showing_session') }}

            <x-slot:actions>
                <x-button.cancel href="{{ route('audit.index', array_filter(['user' => $this->isFilteredToUser() ? $this->userId : null])) }}">
                    {{ __('audit.show_every_session') }}
                </x-button.cancel>
            </x-slot:actions>
        </x-banner>
    @endif

    <x-table-header>
        <x-slot:search>
            <x-table-search id="audit-search" :label="__('audit.search')" />
        </x-slot:search>

        <x-slot:filters>
            <x-table-filter-select
                id="audit-category-filter"
                model="tableFilters.category.value"
                :label="__('audit.category')"
                :placeholder="__('audit.all_categories')"
                :options="$this->categoryOptions()"
                class="min-w-36"
            />

            {{-- A project can log dozens of kinds of event, so this one can be searched. --}}
            <x-listbox
                id="audit-event-filter"
                model="tableFilters.event.value"
                :label="__('audit.event')"
                :placeholder="__('audit.all_events')"
                :options="$this->eventOptions()"
                searchable
                class="min-w-36"
            />

            {{-- Too many people to list up front: it searches on the server as you type. --}}
            <x-listbox
                id="audit-person-filter"
                model="tableFilters.person.value"
                :label="__('audit.person')"
                :placeholder="__('audit.all_people')"
                search="searchPeople"
                :selected-label="$this->personLabel()"
                class="min-w-44 max-w-64"
            />

            <x-table-filter-select
                id="audit-actor-type-filter"
                model="tableFilters.actor_type.value"
                :label="__('audit.actor_type')"
                :placeholder="__('audit.all_actor_types')"
                :options="$this->actorTypeOptions()"
                class="min-w-36"
            />

            <x-date-picker
                id="audit-date-filter"
                model="tableFilters.date.value"
                :label="__('audit.date')"
                clearable
                class="min-w-44"
            />
        </x-slot:filters>

        @if ($this->canExport())
            <x-slot:actions>
                @include('filament.tables.audit-export-form', ['filters' => $this->exportFilters()])
            </x-slot:actions>
        @endif
    </x-table-header>

    {{ $this->table }}

    <x-filament-actions::modals />
</div>
