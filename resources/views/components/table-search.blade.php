{{--
    <x-table-search> — floating-label search input for a <x-table-header>
    `search` slot. Wire `model` to the table's public search property.
--}}
@props([
    'model' => 'tableSearch',
    'label' => null,
    'id' => null,
    'debounce' => '300ms',
])

@php $id ??= \Illuminate\Support\Str::slug(\Illuminate\Support\Str::snake($model)); @endphp

<div {{ $attributes->class('ui-floating-label') }} style="--ui-floating-label-inset: 2.25rem">
    <label class="input w-full">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-4 w-4 opacity-50">
            <circle cx="11" cy="11" r="7" fill="none" stroke="currentColor" stroke-width="1.5"/>
            <line x1="16.5" y1="16.5" x2="21" y2="21" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
        </svg>
        <input id="{{ $id }}" type="search" wire:model.live.debounce.{{ $debounce }}="{{ $model }}" placeholder=" ">
    </label>
    <label for="{{ $id }}" class="ui-floating-label-text">{{ $label }}</label>
</div>
