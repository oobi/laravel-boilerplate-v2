{{--
    <x-form-error for="email" />: a field's validation message, tied to the field for screen
    readers. Renders nothing when the field has no error. The message's id is "{id}-error"
    (id defaults to the field name); give the field aria-invalid="true" and
    aria-describedby="{id}-error" while it has an error, as <x-form-input> does. `bag` names a
    Livewire error bag other than the default.
--}}
@props([
    'for',
    'id' => null,
    'bag' => 'default',
])

@error($for, $bag)
    <p id="{{ $id ?? $for }}-error" {{ $attributes->merge(['class' => 'mt-1 text-sm text-error-text']) }}>{{ $message }}</p>
@enderror
