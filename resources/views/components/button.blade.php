{{--
    <x-button> — daisyUI button, the single button primitive for the app
    shell and any custom (non-Filament-rendered) markup. Filament's own
    action buttons stay native markup, restyled separately in
    filament-buttons.css — that markup isn't ours to swap for this component.

    Props:
    - color: primary, secondary, accent, neutral, info, success, warning, error
      (default: primary) — also accepts Filament's `danger`/`gray` names.
    - variant: solid, soft, outline, dash, ghost (default: solid)
    - size: xs, sm, md, lg, xl (default: none — daisyUI's own default size)
    - href: renders an <a> instead of a <button> when given
    - disabled: bool (default: false)
    - type: button, submit, reset (default: button — ignored when href is given)
--}}
@props([
    'color' => 'primary',
    'variant' => 'solid',
    'size' => null,
    'href' => null,
    'disabled' => false,
    'type' => 'button',
])

@php
    use App\Support\Theme\DaisyColor;

    $daisyColor = DaisyColor::map($color);

    $classes = collect(['btn', "btn-{$daisyColor}"])
        ->when($variant !== 'solid', fn ($classes) => $classes->push("btn-{$variant}"))
        ->when($size, fn ($classes) => $classes->push("btn-{$size}"))
        ->implode(' ');
@endphp

@if ($href && ! $disabled)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" @disabled($disabled) {{ $attributes->class($classes) }}>
        {{ $slot }}
    </button>
@endif
