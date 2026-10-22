{{--
    <x-form-hint for="password">At least 12 characters.</x-form-hint>: guidance under a field,
    tied to it for screen readers. The hint's id is "{id}-hint" (id defaults to the field name);
    add "{id}-hint" to the field's aria-describedby, as <x-form-input hint="..."> does.
--}}
@props([
    'for',
    'id' => null,
])

<p id="{{ $id ?? $for }}-hint" {{ $attributes->merge(['class' => 'mt-1 text-sm text-muted']) }}>{{ $slot }}</p>
