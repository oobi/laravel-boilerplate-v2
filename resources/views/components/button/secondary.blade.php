{{--
    <x-button.secondary> — a non-primary alternative sitting alongside a primary
    action (e.g. show / regenerate recovery codes). Solid secondary colour, the
    natural partner to <x-button.action>'s primary: "primary action → primary,
    secondary action → secondary". A filled brand button, so it never reads like
    <x-button.cancel> (neutral outline). Passes everything through.
    See docs/design-system.md.
--}}
<x-button color="secondary" {{ $attributes }}>{{ $slot }}</x-button>
