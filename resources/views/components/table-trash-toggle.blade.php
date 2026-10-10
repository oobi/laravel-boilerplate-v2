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
    {{-- Named in words: the icon and count alone read as just "106". --}}
    <x-button-group.item :active="$value !== '0'" wire:click="$set('{{ $model }}', '')" :aria-label="trans_choice('admin.active_records_label', $activeCount, ['count' => $activeCount])">
        <x-heroicon-m-check class="h-4 w-4" aria-hidden="true" />
        <x-badge color="info" size="xs">{{ $activeCount }}</x-badge>
    </x-button-group.item>
    @if ($trashedCount > 0)
        <x-button-group.item :active="$value === '0'" color="danger" wire:click="$set('{{ $model }}', '0')" :aria-label="trans_choice('admin.trashed_records_label', $trashedCount, ['count' => $trashedCount])">
            <x-heroicon-o-trash class="h-4 w-4" aria-hidden="true" />
            <x-badge color="error" size="xs">{{ $trashedCount }}</x-badge>
        </x-button-group.item>
    @endif
</x-button-group>
