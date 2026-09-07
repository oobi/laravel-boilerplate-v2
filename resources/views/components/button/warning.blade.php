{{--
    <x-button.warning> — a consequential / sensitive but NOT destructive action:
    impersonate, grant super-admin, reset password. Powerful and worth a pause,
    yet reversible and non-destructive (amber). See docs/design-system.md.
--}}
<x-button color="warning" {{ $attributes }}>{{ $slot }}</x-button>
