{{--
    Avatar component. Renders daisyUI's own <div class="avatar"> structure —
    `.avatar` is patched (daisyui-overrides/avatar.css) to always center its
    content and default to a sensible initials size/shape. `avatar-{color}
    avatar-{variant}` (colors.css) supplies the tint; `.avatar-{xs,sm,lg,xl}` /
    `.avatar-square` are the only modifiers.

    Props:
    - user: a model exposing ->name and ->profile_photo_url — sets name/src
      for you; explicit name/src props (below) always take precedence
    - name: derives initials (and sets the `title` attribute)
    - src: image URL — falls back to initials if absent or if it fails to load
    - size: xs, sm, md, lg, xl (default: md — no class needed, it's the default)
    - variant: solid, soft, ghost, outline (default: soft)
    - color: primary, secondary, accent, neutral, info, success, warning, error (default: neutral)
    - square: bool — rounded-box instead of a circle (default: false)
--}}
@props([
    'user' => null,
    'name' => '',
    'src' => null,
    'size' => 'md',
    'variant' => 'soft',
    'color' => 'neutral',
    'square' => false,
])

@php
    $name = $name ?: ($user->name ?? '');
    $src = $src ?: ($user->profile_photo_url ?? null);

    $initials = collect(preg_split('/\s+/', trim($name)))
        ->filter()
        ->take(2)
        ->map(fn (string $part) => mb_substr($part, 0, 1))
        ->implode('');
    $initials = strtoupper($initials);

    $outerClasses = collect(['avatar'])
        ->when($size !== 'md', fn ($classes) => $classes->push("avatar-{$size}"))
        ->when($square, fn ($classes) => $classes->push('avatar-square'))
        ->implode(' ');
@endphp

<div {{ $attributes->merge(['class' => $outerClasses]) }}>
    <div class="avatar-{{ $color }} avatar-{{ $variant }}" title="{{ $name }}">
        @if ($src)
            <img
                src="{{ $src }}"
                alt="{{ $name }}"
                loading="lazy"
                onerror="this.style.display='none'; this.nextElementSibling.style.removeProperty('display');"
            >
            <span style="display:none;">{{ $initials }}</span>
        @else
            <span>{{ $initials }}</span>
        @endif
    </div>
</div>
