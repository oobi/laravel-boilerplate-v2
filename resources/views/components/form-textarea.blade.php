{{--
    <x-form-textarea> — labelled daisyUI <textarea>, floating or standard.
    Same props/rules as <x-form-input> (see that file). Slot is the textarea's initial content.
--}}
@props([
    'name',
    'label' => null,
    'floating' => true,
])

@php
    $id = $attributes->get('id', $name);
    $textareaClass = 'textarea w-full' . ($floating ? '' : ' ui-form-input') . ($errors->has($name) ? ' textarea-error' : '');
    // An error is announced with the field: aria-invalid, and the message (x-form-error) added to its description.
    $describedBy = trim($attributes->get('aria-describedby', '').($errors->has($name) ? ' '.$id.'-error' : ''));
    $textareaAttributes = $attributes->except(['class', 'id', 'aria-describedby'])->merge(array_filter([
        'class' => $textareaClass,
        'aria-invalid' => $errors->has($name) ? 'true' : null,
        'aria-describedby' => $describedBy ?: null,
    ]));
@endphp

<div {{ $attributes->only('class') }}>
    @if ($floating)
        <div class="ui-floating-label">
            <textarea id="{{ $id }}" placeholder=" " {{ $textareaAttributes }}>{{ $slot }}</textarea>
            <label for="{{ $id }}" class="ui-floating-label-text">{{ $label }}</label>
        </div>
    @else
        <label for="{{ $id }}" class="ui-form-label mb-1">{{ $label }}</label>
        <textarea id="{{ $id }}" {{ $textareaAttributes }}>{{ $slot }}</textarea>
    @endif

    <x-form-error :for="$name" :id="$id" />
</div>
