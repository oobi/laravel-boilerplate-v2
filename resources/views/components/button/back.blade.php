{{--
    <x-button.back> — return to the previous screen (Back to list, Back to user).
    Low-emphasis neutral navigation with a leading back arrow, one step lower in
    emphasis than <x-button.cancel> (neutral outline) so "go back" and "abort this
    form" read differently. Defaults its label to "Back"; pass a slot to override.
    Usually given an `href`; passes everything else through. See docs/design-system.md.
--}}
<x-button color="neutral" variant="ghost" {{ $attributes }}>
    <x-heroicon-o-arrow-left class="size-4" />
    {{ $slot->isEmpty() ? __('admin.back') : $slot }}
</x-button>
