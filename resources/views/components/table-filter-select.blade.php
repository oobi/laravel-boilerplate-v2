{{--
    <x-table-filter-select> — floating-label select for a <x-table-header>
    `filters` slot. `model` is the full wire:model path, e.g.
    "tableFilters.system_role.value". Pass width via `class` (merged onto
    the <select>, not the wrapper), e.g. class="min-w-44".
--}}
@props([
    'model',
    'label' => null,
    'options' => [],
    'placeholder' => null,
    'id' => null,
])

@php $id ??= \Illuminate\Support\Str::slug(str_replace(['.', '_'], '-', $model)); @endphp

<div class="ui-floating-label">
    <select id="{{ $id }}" wire:model.live="{{ $model }}" {{ $attributes->class(['select']) }}>
        @if ($placeholder)
            <option value="">{{ $placeholder }}</option>
        @endif
        @foreach ($options as $value => $optionLabel)
            <option value="{{ $value }}">{{ $optionLabel }}</option>
        @endforeach
    </select>
    <label for="{{ $id }}" class="ui-floating-label-text">{{ $label }}</label>
</div>
