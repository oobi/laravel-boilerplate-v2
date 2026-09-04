{{--
    Canonical logo, shared by the admin shell and the login/public layouts.
    Renders resources/images/app-logo-wide.svg (or app-logo.svg for the icon
    -only mark) — to rebrand, just replace those two files with your own,
    keeping their aspect ratios; no Blade changes needed.
    Extra attributes (class, x-show, href, ...) merge onto the root <a>, so
    callers never need to wrap it in another link element.

    Props:
    - variant: wide (wordmark, default) or icon (mark only, no type)
    - size: Tailwind height utility applied to the <img> (default: h-8)
--}}
@props([
    'variant' => 'wide',
    'size' => 'h-8',
])

@php
    $file = $variant === 'icon' ? 'resources/images/app-logo.svg' : 'resources/images/app-logo-wide.svg';
@endphp

<a {{ $attributes->merge(['href' => url('/'), 'class' => 'inline-flex items-center justify-center gap-2']) }}>
    <img src="{{ Vite::asset($file) }}" alt="{{ config('app.name', 'Laravel') }}" class="{{ $size }} w-auto">
</a>
