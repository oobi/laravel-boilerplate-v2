@props([
    'value' => null,
    'color' => null,
    'size' => null,
    'animation' => null,
])

@php
    use App\Support\Theme\StatusDot;
    use Filament\Support\Contracts\HasColor;
    use Filament\Support\Contracts\HasLabel;

    // A state (Active, Suspended, Pending…): daisyUI's status dot, then its label
    // in plain text, so colour is never the only signal. A kind of thing (a role)
    // is an <x-badge> instead. Like <x-badge>, pass an enum implementing
    // Filament's HasColor/HasLabel as `value` to skip color and label; explicit
    // `color` and slot content win. `size` (xs…xl) and `animation` (ping, bounce)
    // are daisyUI's options; the animation stops for reduced motion.
    $color ??= ($value instanceof HasColor) ? $value->getColor() : 'neutral';
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-2']) }}>
    {{ StatusDot::html($color, $size, $animation) }}
    <span>{{ $slot->isNotEmpty() ? $slot : (($value instanceof HasLabel) ? $value->getLabel() : $value) }}</span>
</span>
