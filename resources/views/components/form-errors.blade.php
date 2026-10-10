{{--
    <x-form-errors :fields="['email', 'password']" />: the error summary at the top of a
    hand-rolled form, for errors that belong to no field on it (a field's own error shows once,
    under the field, via <x-form-error>). Renders nothing when there are none.
--}}
@props([
    'fields' => [],
])

@php
    $unplaced = collect($errors->getMessages())->except($fields)->flatten();
@endphp

@if ($unplaced->isNotEmpty())
    <x-alert color="error" {{ $attributes }}>
        <ul>
            @foreach ($unplaced as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </x-alert>
@endif
