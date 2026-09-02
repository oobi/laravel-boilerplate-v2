{{--
    <x-tabs.nav> — tabs ATTACHED to their content as one bordered "island"
    (never a floating bar over a separate box) — pass the panel via the
    `content` slot. That island can itself nest others (cards, toolbars,
    tables) inside `content`, same as the rest of the design system.

    tabs-border (not tabs-lift) — daisyUI's own "radio tabs-border + tab
    content" example is exactly this pairing; tabs-lift's corner-notch CSS
    is tuned for its content sitting immediately after the checked radio
    among ALL tabs in the DOM, which doesn't hold for us (one tab per page
    load) and showed as a stray seam between the tabs and the panel.
--}}
<div class="tabs-connected overflow-hidden rounded-box border border-base-300 bg-base-100">
    <div class="tabs-connected__header px-3 pt-4 sm:px-6 sm:pt-6">
        <div role="tablist" class="tabs tabs-border gap-2 sm:gap-6">
            {{ $slot }}
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
