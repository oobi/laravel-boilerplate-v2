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

<x-button-group :label="__('admin.records_shown')" {{ $attributes }}>
    <x-button-group.item :active="$value !== '0'" wire:click="$set('{{ $model }}', '')">
        <x-heroicon-m-check class="h-4 w-4" />
        <x-badge color="info" size="xs">{{ $activeCount }}</x-badge>
    </x-button-group.item>
    @if ($trashedCount > 0)
        <x-button-group.item :active="$value === '0'" color="danger" wire:click="$set('{{ $model }}', '0')">
            <x-heroicon-o-trash class="h-4 w-4" />
            <x-badge color="error" size="xs">{{ $trashedCount }}</x-badge>
        </x-button-group.item>
    @endif
</x-button-group>
