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
    // An error is announced with the field: aria-invalid, and the message (x-form-error) added to its description.
    $describedBy = trim($attributes->get('aria-describedby', '').($errors->has($name) ? ' '.$id.'-error' : ''));
    $selectAttributes = $attributes->except(['class', 'id', 'aria-describedby'])->merge(array_filter([
        'class' => $selectClass,
        'aria-invalid' => $errors->has($name) ? 'true' : null,
        'aria-describedby' => $describedBy ?: null,
    ]));
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

    <x-form-error :for="$name" :id="$id" />
</div>
