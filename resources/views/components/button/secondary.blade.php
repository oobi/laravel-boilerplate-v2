{{--
    <x-button.secondary> — a non-primary alternative sitting alongside a primary
    action (e.g. show / regenerate recovery codes). Neutral outline: visible but
    clearly subordinate to <x-button.action>. Passes everything through.
    See docs/design-system.md.
--}}
<x-button color="neutral" variant="outline" {{ $attributes }}>{{ $slot }}</x-button>
