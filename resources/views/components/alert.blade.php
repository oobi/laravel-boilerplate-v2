{{--
    <x-alert> — daisyUI alert, the single flash/inline-message primitive
    across the app.

    Props:
    - color: primary, secondary, accent, neutral, info, success, warning, error
      (default: info) — also accepts Filament's `danger`/`gray` names.
    - variant: solid, soft, outline, dash (default: solid — daisyUI's own
      default alert look; there is no `alert-ghost`)
--}}
@props([
    'color' => 'info',
    'variant' => 'soft',
])

@php
    use App\Support\Theme\DaisyColor;

    $daisyColor = DaisyColor::fromFilamentColor($color)->value;

    $classes = collect(['alert', "alert-{$daisyColor}"])
        ->when($variant !== 'solid', fn ($classes) => $classes->push("alert-{$variant}"))
        ->implode(' ');
@endphp

<div role="alert" {{ $attributes->class($classes) }}>
    {{ $slot }}
</div>
