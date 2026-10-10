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

    // Errors and warnings interrupt (alert); everything else waits its turn (status). A page may pass its own role.
    $role = $attributes->get('role', in_array($daisyColor, ['error', 'warning'], true) ? 'alert' : 'status');

    $classes = collect(['alert', "alert-{$daisyColor}"])
        ->when($variant !== 'solid', fn ($classes) => $classes->push("alert-{$variant}"))
        ->implode(' ');
@endphp

<div role="{{ $role }}" {{ $attributes->except('role')->class($classes) }}>
    {{ $slot }}
</div>
