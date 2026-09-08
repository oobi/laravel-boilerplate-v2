{{--
    <x-action-list> — a group of peer action buttons (Filament actions or
    <x-button.*>) that tile horizontally and wrap, each growing to share the row
    width with even gaps; a button alone on a row fills it. Use for a sidebar
    "Actions" card, a panel's action row, or anywhere a set of sibling actions
    sit together. Children can be raw buttons — the list styles its own direct
    children (so it works with Filament's `{{ $action }}` output too).

    The `basis-40` floor is the wrap threshold: buttons tile across on a wide
    container and stack full-width once only one fits per row.
--}}
<div {{ $attributes->class('flex flex-wrap gap-2 [&>*]:grow [&>*]:basis-40') }}>
    {{ $slot }}
</div>
