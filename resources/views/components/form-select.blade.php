{{--
    <x-form-select> — labelled daisyUI <select>, floating or standard.
    Same props/rules as <x-form-input> (see that file). Slot is the <option> list.
--}}
@props([
    'name',
    'label' => null,
    'floating' => true,
])

@php
    $id = $attributes->get('id', $name);
    $selectClass = 'select w-full' . ($floating ? '' : ' ui-form-input') . ($errors->has($name) ? ' select-error' : '');
    $selectAttributes = $attributes->except(['class', 'id'])->merge(['class' => $selectClass]);
@endphp

<div {{ $attributes->only('class') }}>
    @if ($floating)
        <div class="ui-floating-label">
            <select id="{{ $id }}" {{ $selectAttributes }}>{{ $slot }}</select>
            <label for="{{ $id }}" class="ui-floating-label-text">{{ $label }}</label>
        </div>
    @else
        <label for="{{ $id }}" class="ui-form-label mb-1">{{ $label }}</label>
        <select id="{{ $id }}" {{ $selectAttributes }}>{{ $slot }}</select>
    @endif

    @error($name)
        <p class="mt-1 text-xs text-error">{{ $message }}</p>
    @enderror
</div>
