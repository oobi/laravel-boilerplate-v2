{{--
    <x-button.warning> — a consequential / sensitive but NOT destructive action:
    impersonate, grant super-admin, reset password. Powerful and worth a pause,
    yet reversible and non-destructive (amber). See docs/design-system.md.
--}}
@props(['href' => null])

<x-button color="warning" :href="$href" {{ $attributes }}>{{ $slot }}</x-button>
