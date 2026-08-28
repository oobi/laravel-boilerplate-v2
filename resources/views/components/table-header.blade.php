{{--
    <x-table-header> — reusable, upgrade-proof chrome that sits ABOVE a
    Filament table, replacing Filament's own header/search/filter row
    (hidden via .fi-ta-header-ctn { display: none } — see
    theme/components/ui/admin-table.css). Wire the slots to the table's
    PUBLIC state (wire:model.live="tableSearch", wire:model.live=
    "tableFilters.{name}.value") rather than overriding Filament's DOM.

    Slots:
      - search  : the search input
      - filters : filter selects / toggles
      - counts  : record counts (e.g. total / trashed)
      - actions : header actions (create, export, ...)
--}}
@props([
    'search' => null,
    'filters' => null,
    'counts' => null,
    'actions' => null,
])

<div {{ $attributes->class('ui-toolbar') }}>
    @isset($search)
        <div class="ui-toolbar__search">
            {{ $search }}
        </div>
    @endisset

    @isset($filters)
        <div class="ui-toolbar__filters">
            {{ $filters }}
        </div>
    @endisset

    @isset($counts)
        <div class="ui-toolbar__counts">
            {{ $counts }}
        </div>
    @endisset

    @isset($actions)
        <div class="ui-toolbar__actions">
            {{ $actions }}
        </div>
    @endisset
</div>
