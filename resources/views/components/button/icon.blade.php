{{--
    <x-button.icon> — square (or circle) icon-only chrome button: menu toggles,
    modal close, pin / unpin. Plain ghost (base content, no colour tint) so it
    reads as chrome, not an intent action. Slot is the icon.

    Always give it an accessible name (`aria-label`, or a `title`) — an icon-only
    button has no text. Passes through everything else (`@click`, `x-show`,
    `wire:click`, extra classes, …).

    Props:
    - circle: bool (default false → square)
    - size: xs | sm | md | lg | xl (default sm)
--}}
@props([
    'circle' => false,
    'size' => 'sm',
])

<button
    type="button"
    {{ $attributes->class([
        'btn btn-ghost',
        'btn-circle' => $circle,
        'btn-square' => ! $circle,
        'btn-'.$size => $size,
    ]) }}
>
    {{ $slot }}
</button>
