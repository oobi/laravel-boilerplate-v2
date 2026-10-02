{{--
    <x-button.cancel> — dismiss / abort (cancel, close, discard). One canonical
    cancel treatment (neutral outline) so every cancel looks identical. Defaults
    its label to "Cancel"; pass a slot to override. See docs/design-system.md.
--}}
@props(['href' => null])

<x-button color="neutral" variant="outline" :href="$href" {{ $attributes }}>{{ $slot->isEmpty() ? __('admin.cancel') : $slot }}</x-button>
