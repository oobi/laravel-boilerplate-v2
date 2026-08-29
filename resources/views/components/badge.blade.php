@props([
    'value' => null,
    'color' => null,
    'variant' => 'soft',
    'size' => null,
])

@php
    use Filament\Support\Contracts\HasColor;
    use Filament\Support\Contracts\HasLabel;

    // Pass an enum (or anything else implementing Filament's HasColor/
    // HasLabel) as `value` to skip specifying color/slot content manually.
    // Explicit `color` / slot content always win when given.
    $color ??= ($value instanceof HasColor) ? $value->getColor() : 'neutral';

    // Enums typically expose Filament's palette names (danger/gray) via
    // getColor() so they "just work" in Filament tables too — map those onto
    // daisyUI's equivalent color names here rather than at every call site.
    $daisyColor = match ($color) {
        'danger' => 'error',
        'gray' => 'neutral',
        default => $color,
    };

    // No explicit size keeps the original fixed text-xs look (default: xs,
    // sm, md, lg, xl); a size lets daisyUI's own badge-{size} control it instead.
    $sizeClass = $size ? "badge-{$size}" : 'text-xs';
@endphp

<span {{ $attributes->merge(['class' => "badge badge-{$daisyColor} badge-{$variant} {$sizeClass}"]) }}>
    {{ $slot->isNotEmpty() ? $slot : (($value instanceof HasLabel) ? $value->getLabel() : $value) }}
</span>
