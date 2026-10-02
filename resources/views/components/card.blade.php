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
    - accent: optional daisyUI colour (primary, secondary, accent, neutral, info,
      success, warning, error) for a thick top edge that marks the card's
      status, e.g. a warning that needs attention, while its text stays in the
      body colour (default: none)
    - bodyClass: extra classes on `.card-body` (default: gap-3)

    Slots:
    - default: card body content
    - actions: optional content (e.g. a "View all" link) rendered inline with
      the title, right-aligned
    - footer: optional row below the body, under a divider, for the card's
      actions; its attributes merge onto the row
    - stickyFooter: keep the footer in view at the bottom of the screen while
      a long card scrolls past (default: false). It sticks within the nearest
      scrolling ancestor, so nothing between the card and the page may set
      overflow hidden/auto (use overflow-clip to clip corners instead)
--}}
@props([
    'title' => null,
    'type' => 'default',
    'titleClass' => null,
    'bodyClass' => 'gap-3',
    'bordered' => true,
    'inset' => false,
    'accent' => null,
    'stickyFooter' => false,
])

@php
    $titleClasses = [
        'default' => 'card-title',
        'panel' => 'text-sm font-semibold text-base-content',
    ];

    $titleClass ??= $titleClasses[$type] ?? 'card-title';

    // Whole class names, so Tailwind finds them.
    $accentClasses = [
        'primary' => 'border-t-4 border-t-primary',
        'secondary' => 'border-t-4 border-t-secondary',
        'accent' => 'border-t-4 border-t-accent',
        'neutral' => 'border-t-4 border-t-neutral',
        'info' => 'border-t-4 border-t-info',
        'success' => 'border-t-4 border-t-success',
        'warning' => 'border-t-4 border-t-warning',
        'error' => 'border-t-4 border-t-error',
    ];
    $accentClass = $accent === null ? null : $accentClasses[\App\Support\Theme\DaisyColor::fromFilamentColor($accent)->value];
@endphp

<div {{ $attributes->class(['card', 'bg-base-100' => ! $inset, 'card-inset' => $inset, 'border border-base-300' => $bordered, $accentClass]) }}>
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

    @isset($footer)
        <div {{ $footer->attributes->class([
            'flex flex-wrap items-center gap-x-6 gap-y-3 border-t border-base-300 px-[var(--card-p,1.5rem)] py-4',
            // Solid, rounded like the card's bottom, with a soft edge so content reads as passing under it.
            'sticky bottom-0 z-10 rounded-b-box bg-inherit shadow-[0_-6px_12px_-10px_rgb(0_0_0/0.25)]' => $stickyFooter,
        ]) }}>
            {{ $footer }}
        </div>
    @endisset
</div>
