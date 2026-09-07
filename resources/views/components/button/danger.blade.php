{{--
    <x-button.danger> — a destructive / irreversible action: delete, force-delete,
    purge, force-disable 2FA, suspend, revoke. Anything that removes data or cuts
    off access. Not just "delete". See docs/design-system.md for danger vs warning.
--}}
<x-button color="error" {{ $attributes }}>{{ $slot }}</x-button>
