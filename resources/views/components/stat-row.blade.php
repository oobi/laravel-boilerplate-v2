{{--
    <x-stat-row> — one label/value line inside a <x-stats-list>. Renders a
    `<dt>` (icon + label) and `<dd>` (value) grouped in a flex row.

    Props:
    - label: the stat's name (left)
    - value: the stat's value (right); or pass it as the default slot for
      markup (e.g. a <x-badge>)
    - icon: optional heroicon component name, e.g. 'heroicon-o-calendar-days'
    - muted: render the value in the subtle text color — use for an absent /
      "Never" / zero-state value
--}}
@props([
    'label' => '',
    'value' => null,
    'icon' => null,
    'muted' => false,
])

<div class="flex items-center justify-between gap-3">
    <dt class="ui-subtle flex items-center gap-2">
        @if ($icon)
            <x-dynamic-component :component="$icon" class="h-4 w-4 shrink-0 opacity-70" />
        @endif
        {{ $label }}
    </dt>

    <dd @class([
        'text-right text-sm font-medium',
        'text-base-content' => ! $muted,
        'text-base-content/50' => $muted,
    ])>
        {{ $value ?? $slot }}
    </dd>
</div>
