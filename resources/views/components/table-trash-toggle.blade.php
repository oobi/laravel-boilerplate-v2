{{--
    <x-table-trash-toggle> — active/trashed record-count toggle for a
    <x-table-header> `counts` slot, wired to a Filament TrashedFilter's
    "tableFilters.{key}.value". Renders nothing while the trash is empty
    (a lone "active" button switches to nothing), unless the trash view is
    already selected. In a phone's filter panel it goes full width, its
    segments say "Active" and "Trashed", under a "Showing" label
    (toolbar.css); elsewhere just the icons and counts.
--}}
@props([
    'model' => 'tableFilters.trashed.value',
    'value' => '',
    'activeCount' => 0,
    'trashedCount' => 0,
])

@if ($trashedCount > 0 || $value === '0')
    <div class="ui-floating-label ui-trash-toggle" data-table-filter="{{ $model }}">
        <x-button-group :label="__('admin.records_shown')" data-floating-label-up {{ $attributes }}>
            {{-- Named in words: the icon and count alone read as just "106". The visible word is part of the name. --}}
            <x-button-group.item :active="$value !== '0'" wire:click="$set('{{ $model }}', '')" :aria-label="trans_choice('admin.active_records_label', $activeCount, ['count' => $activeCount])">
                <x-heroicon-m-check class="h-4 w-4" aria-hidden="true" />
                <span class="ui-trash-toggle__word">{{ __('admin.records_active') }}</span>
                <x-badge color="info" size="xs">{{ $activeCount }}</x-badge>
            </x-button-group.item>
            @if ($trashedCount > 0)
                <x-button-group.item :active="$value === '0'" color="danger" wire:click="$set('{{ $model }}', '0')" :aria-label="trans_choice('admin.trashed_records_label', $trashedCount, ['count' => $trashedCount])">
                    <x-heroicon-o-trash class="h-4 w-4" aria-hidden="true" />
                    <span class="ui-trash-toggle__word">{{ __('admin.records_trashed') }}</span>
                    <x-badge color="error" size="xs">{{ $trashedCount }}</x-badge>
                </x-button-group.item>
            @endif
        </x-button-group>
        <span class="ui-floating-label-text ui-trash-toggle__label" aria-hidden="true">{{ __('admin.showing') }}</span>
    </div>
@endif
