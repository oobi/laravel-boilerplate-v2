{{--
    Dashboard stat card — label/value with an optional icon and colored
    footer note. Built on daisyUI's real `card` primitive (not a parallel
    `.ui-card` system). Mirrors Buzz's <x-stats-card>.

    Props:
    - label: small heading above the value
    - value: the headline number/text
    - color: daisyUI semantic color for the icon (primary, secondary, accent,
      neutral, info, success, warning, error) — default info
    - footerColor: semantic color for the footer note text, default neutral (muted text)

    Two to a row on a phone: tighter padding there, and the icon (decorative)
    only from `sm`, so the value keeps the width.
--}}
@props([
    'label' => '',
    'value' => '',
    'color' => 'info',
    'footerColor' => 'neutral',
])

<div {{ $attributes->merge(['class' => 'card bg-base-100 border border-base-300']) }}>
    <div class="card-body max-sm:p-4">
        <div class="flex items-center justify-between gap-3">
            <div class="min-w-0">
                <div class="ui-subtle">{{ $label }}</div>
                <div class="ui-h2">{{ $value }}</div>
            </div>
            @isset($icon)
                <div class="text-{{ $color }} max-sm:hidden" aria-hidden="true">
                    {{ $icon }}
                </div>
            @endisset
        </div>

        @isset($footer)
            {{-- Neutral reads as muted text: the neutral token itself misses AA as text on the dark page. --}}
            <div @class(['mt-2 text-sm', $footerColor === 'neutral' ? 'text-muted' : 'text-'.$footerColor])>
                {{ $footer }}
            </div>
        @endisset
    </div>
</div>
