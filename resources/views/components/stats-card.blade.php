{{--
    Dashboard stat card — label/value with an optional icon and colored
    footer note. Built on daisyUI's real `card` primitive (not a parallel
    `.ui-card` system). Mirrors Buzz's <x-stats-card>.

    Props:
    - label: small heading above the value
    - value: the headline number/text
    - color: daisyUI semantic color for the icon (primary, secondary, accent,
      neutral, info, success, warning, error) — default info
    - footerColor: semantic color for the footer note text — default neutral
--}}
@props([
    'label' => '',
    'value' => '',
    'color' => 'info',
    'footerColor' => 'neutral',
])

<div {{ $attributes->merge(['class' => 'card bg-base-100']) }}>
    <div class="card-body">
        <div class="flex items-center justify-between">
            <div>
                <div class="ui-subtle">{{ $label }}</div>
                <div class="ui-h2">{{ $value }}</div>
            </div>
            @isset($icon)
                <div class="text-{{ $color }}">
                    {{ $icon }}
                </div>
            @endisset
        </div>

        @isset($footer)
            <div class="mt-2 text-sm text-{{ $footerColor }}">
                {{ $footer }}
            </div>
        @endisset
    </div>
</div>
