{{--
    <x-tabs-item> — a single tab link. Pass `badge` (e.g. a record count) to
    show a small <x-badge> after the label — tabs can carry a count just
    like a sidebar nav item can.

    Props:
    - active: bool — the current tab
    - href: the tab's URL
    - icon: optional heroicon component name
    - badge: optional string|int shown as a small badge after the label
--}}
@props([
    'active' => false,
    'href' => '#',
    'icon' => null,
    'badge' => null,
])

<a
    href="{{ $href }}"
    role="tab"
    @if ($active) aria-current="page" @endif
    {{ $attributes->class(['tab', 'gap-2', 'tab-active' => $active]) }}
>
    @if ($icon)
        <x-dynamic-component :component="$icon" class="h-4 w-4" />
    @endif

    {{ $slot }}

    @if (! is_null($badge))
        <x-badge variant="soft" size="xs" color="primary">{{ $badge }}</x-badge>
    @endif
</a>
