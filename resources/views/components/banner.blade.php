{{--
    Banner component — a persistent, contextual banner (announcements,
    "trial ending", "update available", …), as opposed to a daisyUI `alert`,
    which is for short-lived flash messages.

    Props:
    - variant: primary, secondary, accent, neutral, info, success, warning, error (default: info)
    - dismissible: bool — adds a close button, needs Alpine (x-data/x-show)

    Slots:
    - default: banner body (title/description)
    - actions: optional action buttons, rendered at the end of the banner
--}}
@props([
    'variant' => 'info',
    'dismissible' => false,
])

@php
    $icons = [
        'info' => '<circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="1.5"/><circle cx="12" cy="8.2" r="0.9" fill="currentColor"/><line x1="12" y1="11" x2="12" y2="16" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>',
        'success' => '<circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="1.5"/><path d="M8.5 12.5l2.5 2.5 4.5-5" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>',
        'warning' => '<path d="M12 3.5 21 19H3z" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><line x1="12" y1="9" x2="12" y2="14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><circle cx="12" cy="16.75" r="0.9" fill="currentColor"/>',
        'error' => '<circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="1.5"/><line x1="9" y1="9" x2="15" y2="15" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><line x1="15" y1="9" x2="9" y2="15" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>',
    ];
    $iconPath = $icons[$variant] ?? null;
@endphp

<div {{ $attributes->merge(['class' => 'ui-banner ui-banner-'.$variant]) }}
    @if ($dismissible) x-data="{ show: true }" x-show="show" x-transition @endif>
    @if ($iconPath)
        <div class="ui-banner-icon">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-6 w-6">{!! $iconPath !!}</svg>
        </div>
    @endif

    <div class="ui-banner-body">
        {{ $slot }}
    </div>

    @isset($actions)
        <div class="ui-banner-actions">
            {{ $actions }}
        </div>
    @endisset

    @if ($dismissible)
        <button type="button" class="ui-banner-close" @click="show = false" aria-label="Dismiss">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-4 w-4">
                <line x1="6" y1="6" x2="18" y2="18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                <line x1="18" y1="6" x2="6" y2="18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
            </svg>
        </button>
    @endif
</div>
