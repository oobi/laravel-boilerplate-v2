{{--
    <x-card> — daisyUI card wrapper with an optional title/actions header, for
    the "section of related content" blocks repeated throughout the
    theme-demo gallery and dashboard-style pages.

    Props:
    - title: optional heading, rendered inside an `<h2>`
    - type: 'default' (bold `.card-title`) or 'panel' (smaller `text-sm
      font-semibold` heading, one tier below the default — used for Users Show
      page sidebar panel cards). Picks `titleClass` for you; use this instead
      of guessing a heading class.
    - titleClass: explicit override for the `<h2>` class, escape hatch for a
      one-off heading style not covered by `type`
    - bordered: adds `border border-base-300` (default: true) — every card
      should have a border; only set false when nesting a card inside
      another card, to drop the doubled-up border
    - inset: recesses the card for nesting inside another card/panel — drops
      the island shadow and shades it to `base-200` so it reads as a well in
      the parent rather than a second floating island (default: false)
    - bodyClass: extra classes on `.card-body` (default: gap-3)

    Slots:
    - default: card body content
    - actions: optional content (e.g. a "View all" link) rendered inline with
      the title, right-aligned
--}}
@props([
    'title' => null,
    'type' => 'default',
    'titleClass' => null,
    'bodyClass' => 'gap-3',
    'bordered' => true,
    'inset' => false,
])

@php
    $titleClasses = [
        'default' => 'card-title',
        'panel' => 'text-sm font-semibold text-base-content',
    ];

    $titleClass ??= $titleClasses[$type] ?? 'card-title';
@endphp

<div {{ $attributes->class(['card', 'bg-base-100' => ! $inset, 'card-inset' => $inset, 'border border-base-300' => $bordered]) }}>
    <div class="card-body {{ $bodyClass }}">
        @if (isset($title) || isset($actions))
            <div class="flex items-center justify-between">
                @isset($title)
                    <h2 class="{{ $titleClass }}">{{ $title }}</h2>
                @endisset

                @isset($actions)
                    {{ $actions }}
                @endisset
            </div>
        @endif

        {{ $slot }}
    </div>
</div>
