{{--
    <x-table-header> — reusable, upgrade-proof chrome that sits ABOVE a
    Filament table, replacing Filament's own header/search/filter row
    (hidden via .fi-ta-header-ctn { display: none } — see
    theme/components/ui/filament-table.css). Wire the slots to the table's
    PUBLIC state (wire:model.live="tableSearch", wire:model.live=
    "tableFilters.{name}.value") rather than overriding Filament's DOM.

    Slots:
      - search  : the search input
      - filters : filter selects / toggles
      - counts  : record counts (e.g. total / trashed)
      - actions : header actions (create, export, ...)

    On a phone the filters and counts fold into a panel behind a "Filters"
    button beside the search. The button counts the filters that are on: every
    control with a data-table-filter="{wire:model path}" whose value isn't
    empty (the table-filter-select, listbox, date-picker and trash-toggle
    components mark themselves). It starts open when one is on.
--}}
@props([
    'search' => null,
    'filters' => null,
    'counts' => null,
    'actions' => null,
])

@php
    $hasFilters = isset($filters) && $filters->hasActualContent();
    $hasCounts = isset($counts) && $counts->hasActualContent();
    $hasPanel = $hasFilters || $hasCounts;
@endphp

<div
    {{ $attributes->class('ui-toolbar') }}
    {{-- Always present, so a panel a later render adds (the trash toggle after a first delete) has its state. --}}
    x-data="{
        open: false,
        filtersOn: @js(__('admin.filters_on', ['count' => '__COUNT__'])),
        init() { this.open = this.activeCount > 0 },
        get activeCount() {
            return [...this.$root.querySelectorAll('[data-table-filter]')].filter((el) => {
                const value = this.$wire.get(el.dataset.tableFilter);

                return Array.isArray(value) ? value.length > 0 : value !== null && value !== undefined && value !== '';
            }).length;
        },
    }"
    x-id="['table-filters']"
>
    @isset($search)
        <div class="ui-toolbar__search">
            {{ $search }}
        </div>
    @endisset

    @if ($hasPanel)
        {{-- With a filter on, the button is named "Filters, 1 on": the visible word first, then the badge's count. --}}
        <x-button
            color="neutral"
            variant="outline"
            class="ui-toolbar__filter-toggle"
            x-on:click="open = ! open"
            aria-expanded="false"
            x-bind:aria-expanded="open.toString()"
            x-bind:aria-controls="$id('table-filters')"
            x-bind:class="{ 'is-active': open || activeCount > 0 }"
            x-bind:aria-label="activeCount > 0 ? filtersOn.replace('__COUNT__', activeCount) : null"
        >
            <x-heroicon-o-adjustments-horizontal class="size-5" aria-hidden="true" />
            {{ __('admin.filters') }}
            <x-badge color="primary" variant="solid" size="sm" x-show="activeCount > 0" x-cloak aria-hidden="true" x-text="activeCount"></x-badge>
            <x-heroicon-m-chevron-down class="ui-toolbar__filter-chevron size-4" aria-hidden="true" />
        </x-button>

        <div class="ui-toolbar__panel" x-bind:id="$id('table-filters')" x-bind:class="{ 'is-open': open }">
            @if ($hasFilters)
                <div class="ui-toolbar__filters">
                    {{ $filters }}
                </div>
            @endif

            @if ($hasCounts)
                <div class="ui-toolbar__counts">
                    {{ $counts }}
                </div>
            @endif
        </div>
    @endif

    @isset($actions)
        <div class="ui-toolbar__actions">
            {{ $actions }}
        </div>
    @endisset
</div>
