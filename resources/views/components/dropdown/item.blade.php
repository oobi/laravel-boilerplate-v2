{{--
    <x-dropdown.item>: one action in an <x-dropdown>. Passes everything
    (wire:click, ...) to its button.

    Props:
    - color: warning or danger for an action with that intent (Retire, Delete),
      the same rubric as the button intents; none for an ordinary action
--}}
@props(['color' => null])

<li>
    <button type="button" {{ $attributes->class([
        'text-warning' => $color === 'warning',
        'text-error-text' => $color === 'danger',
    ]) }}>{{ $slot }}</button>
</li>
