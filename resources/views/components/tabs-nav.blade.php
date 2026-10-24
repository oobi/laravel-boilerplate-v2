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

    flush: content that runs edge to edge, e.g. a table as a tab's panel.
    Drops the panel's padding, runs the line under the tabs the
    full width, and lets a table inside drop its own border and corners
    (`.ui-flush-content`, filament-table.css) so the panel's border frames it.

    scrollable (default true): one horizontal row at every screen size.
    Overflow controls sit beside the viewport in space reserved from first paint.
    Explicit false retains the plain, wrapping tab layout without scroll controls.
    Pages don't pass it, so this default sets the behaviour app-wide.
--}}
@props(['scrollable' => true, 'flush' => false])
{{-- overflow-clip keeps sticky content inside the panel attached to the page scroller. --}}
<div {{ $attributes->class(['tabs-connected overflow-clip rounded-box border border-base-300 bg-base-100 ui-island-shadow']) }}>
    <div class="tabs-connected__header px-3 pt-3 sm:px-6 sm:pt-3">
        <div class="tabs-connected__scroll-shell @if ($scrollable) tabs-connected__scroll-shell--scrollable @endif">
            <div
                class="tabs-connected__scroll-viewport @if ($scrollable) tabs-connected__scroll-viewport--scrollable @endif"
                @if ($scrollable) data-tab-scroll @endif
            >
                <div role="tablist" class="tabs tabs-border gap-2 sm:gap-6 @if ($scrollable) tabs--scrollable @endif">
                    {{ $slot }}
                </div>
            </div>

            @if ($scrollable)
                {{-- Visibility changes without removing the space reserved for these controls. --}}
                <div class="tab-scroll-controls" style="visibility: hidden;">
                    <x-button.icon class="tab-scroll-left" :aria-label="__('admin.scroll_tabs_left')" disabled>
                        <x-heroicon-o-chevron-left class="h-4 w-4" aria-hidden="true" />
                    </x-button.icon>
                    <x-button.icon class="tab-scroll-right" :aria-label="__('admin.scroll_tabs_right')" disabled>
                        <x-heroicon-o-chevron-right class="h-4 w-4" aria-hidden="true" />
                    </x-button.icon>
                </div>
            @endif
        </div>

        @unless ($flush)
            <div class="tabs-connected__divider border-b border-base-300"></div>
        @endunless
    </div>

    @if ($flush)
        {{-- Full width, so it meets the edges like the flush content below. --}}
        <div class="tabs-connected__divider border-b border-base-300"></div>
    @endif

    @isset($content)
        {{-- Forces display regardless of which tab is "active" — daisyUI's own
             show/hide-on-checked wiring is for co-located panels; ours are
             separate pages, so there's always exactly one panel to show. --}}
        <div @class(['tab-content tabs-connected__content bg-base-100', 'p-6' => ! $flush, 'ui-flush-content' => $flush]) style="display: block">
            {{ $content }}
        </div>
    @endisset
</div>
