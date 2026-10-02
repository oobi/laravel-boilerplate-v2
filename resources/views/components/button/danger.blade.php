{{--
    <x-button.danger> — a destructive / irreversible action: delete, force-delete,
    purge, force-disable 2FA, suspend, revoke. Anything that removes data or cuts
    off access. Not just "delete". See docs/design-system.md for danger vs warning.
--}}
@props(['href' => null])

<x-button color="error" :href="$href" {{ $attributes }}>{{ $slot }}</x-button>
