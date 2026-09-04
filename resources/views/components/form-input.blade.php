{{--
    <x-form-input> — labelled daisyUI <input>, floating or standard.

    Props:
    - name: field name — used for id/for and to look up @error messages.
    - label: label text.
    - floating: bool (default true) — floating-label style vs a plain label above the field, styled
      to match Filament's own form fields (see `.ui-form-label`/`.ui-form-input` in daisyui-overrides/forms.css).

    Everything else (type, wire:model, placeholder override, ...) is passed straight through to
    the <input>. Pass a `class` to size/place the OUTER wrapper (e.g. "md:col-span-2"), not the input.
--}}
@props([
    'name',
    'label' => null,
    'floating' => true,
])

@php
    $id = $attributes->get('id', $name);
    $inputClass = 'input w-full' . ($floating ? '' : ' ui-form-input') . ($errors->has($name) ? ' input-error' : '');
    $inputAttributes = $attributes->except(['class', 'id'])->merge(['class' => $inputClass]);
@endphp

<div {{ $attributes->only('class') }}>
    @if ($floating)
        <div class="ui-floating-label">
            <input id="{{ $id }}" placeholder=" " {{ $inputAttributes }}>
            <label for="{{ $id }}" class="ui-floating-label-text">{{ $label }}</label>
        </div>
    @else
        <label for="{{ $id }}" class="ui-form-label mb-1">{{ $label }}</label>
        <input id="{{ $id }}" {{ $inputAttributes }}>
    @endif

    @error($name)
        <p class="mt-1 text-xs text-error">{{ $message }}</p>
    @enderror
</div>
