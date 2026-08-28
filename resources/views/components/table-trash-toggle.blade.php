{{--
    <x-table-trash-toggle> — active/trashed record-count toggle for a
    <x-table-header> `counts` slot, wired to a Filament TrashedFilter's
    "tableFilters.{key}.value". The trashed button only renders once there
    are trashed records to switch to.
--}}
@props([
    'model' => 'tableFilters.trashed.value',
    'value' => '',
    'activeCount' => 0,
    'trashedCount' => 0,
])

<div {{ $attributes->class('ui-button-group') }} role="group">
    <button
        type="button"
        wire:click="$set('{{ $model }}', '')"
        class="ui-button-group-btn {{ $value !== '0' ? 'ui-button-group-btn-active-primary' : '' }}"
    >
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-4 w-4"><path d="M5 12.5l4 4 10-10" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        <x-badge color="{{ $value !== '0' ? 'primary' : 'neutral' }}">{{ $activeCount }}</x-badge>
    </button>

    @if ($trashedCount > 0)
        <button
            type="button"
            wire:click="$set('{{ $model }}', '0')"
            class="ui-button-group-btn {{ $value === '0' ? 'ui-button-group-btn-active-danger' : '' }}"
        >
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-4 w-4"><path d="M4 7h16M9 7V5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2m-9 0 1 12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-12" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            <x-badge color="{{ $value === '0' ? 'error' : 'neutral' }}">{{ $trashedCount }}</x-badge>
        </button>
    @endif
</div>
