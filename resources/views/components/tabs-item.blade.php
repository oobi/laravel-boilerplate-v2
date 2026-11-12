{{--
    <x-tabs-item>: a single tab. With `href` it's a link to another page (the
    usual case: one tab per page). Without, it's a button for switching panels
    on the same page: pass `active` from the server (a Livewire property) and
    it sets `aria-selected` true or false, or wire it with Alpine and bind
    `tab-active` and `aria-selected` yourself, e.g.
    <x-tabs-item x-on:click="tab = 'notes'" x-bind:class="{ 'tab-active': tab === 'notes' }" x-bind:aria-selected="tab === 'notes'">
    Pass `badge` (e.g. a record count) to show a small <x-badge> after the
    label: tabs can carry a count just like a sidebar nav item can. Button
    tabs are each a Tab stop; arrow keys don't move between them. Give each an
    `id` and pass the active one's to <x-tabs-nav panel-labelled-by> so the
    panel is named by it.

    Props:
    - active: bool, the current tab (a link gets aria-current, a button aria-selected)
    - href: the tab's URL; omit for an in-page button
    - icon: optional heroicon component name
    - badge: optional string|int shown as a small badge after the label
--}}
@props([
    'active' => false,
    'href' => null,
    'icon' => null,
    'badge' => null,
])

@php($tag = $href === null ? 'button' : 'a')

<{{ $tag }}
    @if ($href === null) type="button" @else href="{{ $href }}" @endif
    role="tab"
    @if ($href !== null && $active) aria-current="page" @elseif ($href === null) aria-selected="{{ $active ? 'true' : 'false' }}" @endif
    {{ $attributes->class(['tab', 'gap-2', 'tab-active' => $active]) }}
>
    @if ($icon)
        <x-dynamic-component :component="$icon" class="h-4 w-4" />
    @endif

    {{ $slot }}

    @if (! is_null($badge))
        <x-badge variant="soft" size="xs" color="primary">{{ $badge }}</x-badge>
    @endif
</{{ $tag }}>
