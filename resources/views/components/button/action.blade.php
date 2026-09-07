{{--
    <x-button.action> — the affirmative "do it" button: save, create, submit,
    confirm, impersonate. The default, neutral-weight action (primary).
    See docs/design-system.md for when to reach for each semantic button.
    Passes through everything (wire:click, type, href, disabled, size, ...).
--}}
<x-button color="primary" {{ $attributes }}>{{ $slot }}</x-button>
