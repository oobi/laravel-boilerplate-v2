{{--
    <x-empty-state>: the dashed "nothing here yet" panel, for an empty list, a
    page still to come, or a search that found nothing. Use it instead of
    hand-building a dashed box.

    Props:
    - icon: a heroicon name, shown in an <x-avatar icon> above (optional)
    - title: a short heading (optional)

    Slots:
    - default: the explanation, muted
    - actions: optional buttons underneath (e.g. "Add the first one")
--}}
@props([
    'icon' => null,
    'title' => null,
])

<div {{ $attributes->class('flex flex-col items-center gap-3 rounded-box border border-dashed border-base-300 p-8 text-center') }}>
    @if ($icon)
        <x-avatar :icon="$icon" size="lg" />
    @endif

    @if ($title)
        <p class="font-semibold text-base-content">{{ $title }}</p>
    @endif

    @if ($slot->isNotEmpty())
        <div class="text-sm text-muted">{{ $slot }}</div>
    @endif

    @isset($actions)
        <div class="flex flex-wrap justify-center gap-2">{{ $actions }}</div>
    @endisset
</div>
