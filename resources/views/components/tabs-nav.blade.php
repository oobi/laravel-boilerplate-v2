{{--
    <x-tabs-nav> — tabs ATTACHED to their content as one bordered "island"
    (never a floating bar over a separate box) — pass the panel via the
    `content` slot. That island can itself nest others (cards, toolbars,
    tables) inside `content`, same as the rest of the design system.

    tabs-border (not tabs-lift) — daisyUI's own "radio tabs-border + tab
    content" example is exactly this pairing; tabs-lift's corner-notch CSS
    is tuned for its content sitting immediately after the checked radio
    among ALL tabs in the DOM, which doesn't hold for us (one tab per page
    load) and showed as a stray seam between the tabs and the panel.

    Optional scrollable tabs on mobile:
    - Use scrollable="true" attribute to enable horizontal scroll/swipe behavior
    - On mobile: tabs scroll horizontally with "Swipe for more tabs" hint
    - On desktop: all tabs visible with optional scroll indicators
    - Example: <x-tabs-nav scrollable="true">{{ $slot }}</x-tabs-nav>
--}}
@props(['scrollable' => true])
<div class="tabs-connected overflow-hidden rounded-box border border-base-300 bg-base-100">
    @if ($scrollable)
        {{-- Mobile swipe hint --}}
        <div class="block sm:hidden text-center text-xs text-base-content/50 pt-3 px-4">
            <div class="inline-flex items-center justify-center gap-1 bg-base-200/50 rounded-full py-1 px-3">
                <svg class="w-3 h-3 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
                <span class="font-medium">Swipe for more tabs</span>
                <svg class="w-3 h-3 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
            </div>
        </div>
    @endif

    <div class="tabs-connected__header @if ($scrollable) tabs-connected__header--scrollable @endif px-3 pt-3 sm:px-6 sm:pt-3">
        <div class="tabs-connected__scroll-shell @if ($scrollable) tabs-connected__scroll-shell--scrollable @endif">
            {{-- data-tab-scroll must sit on the overflow container, one level
                 above [role="tablist"], so tab-scroll.js can query it as a
                 descendant rather than querying the element itself. --}}
            <div
                class="tabs-connected__scroll-viewport @if ($scrollable) tabs-connected__scroll-viewport--scrollable @endif"
                @if ($scrollable) data-tab-scroll @endif
            >
                <div role="tablist" class="tabs tabs-border gap-2 sm:gap-6 @if ($scrollable) tabs--scrollable @endif">
                    {{ $slot }}
                </div>
            </div>

            @if ($scrollable)
                <div class="tab-scroll-indicator tab-scroll-left" style="display:none;">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </div>
                <div class="tab-scroll-indicator tab-scroll-right">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </div>
            @endif
        </div>

        <div class="tabs-connected__divider border-b border-base-300"></div>
    </div>

    @isset($content)
        {{-- Forces display regardless of which tab is "active" — daisyUI's own
             show/hide-on-checked wiring is for co-located panels; ours are
             separate pages, so there's always exactly one panel to show. --}}
        <div class="tab-content tabs-connected__content bg-base-100 p-6" style="display: block">
            {{ $content }}
        </div>
    @endisset
</div>
