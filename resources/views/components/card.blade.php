{{--
    <x-card> — bordered daisyUI card wrapper with an optional title, for the
    "section of related content" blocks repeated throughout the theme-demo
    gallery (and any other bordered content block).

    Props:
    - title: optional heading rendered as `.card-title` above the slot content
    - bodyClass: extra classes on `.card-body` (default: gap-3)
--}}
@props([
    'title' => null,
    'bodyClass' => 'gap-3',
])

<div {{ $attributes->class(['card bg-base-100 border border-base-300']) }}>
    <div class="card-body {{ $bodyClass }}">
        @isset($title)
            <h2 class="card-title">{{ $title }}</h2>
        @endisset

        {{ $slot }}
    </div>
</div>
