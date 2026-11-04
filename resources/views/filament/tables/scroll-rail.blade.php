{{--
    Rendered after a Filament table's content by the render hook in
    AppServiceProvider, so a Livewire re-render keeps it. While the table is
    wider than its space: fades on the sides with more to see, and a rail above
    the pagination that shows where you are, always visible, unlike a native
    scrollbar (hidden on touch screens and off screen on a long table). The
    arrows scroll by keyboard or a click; dragging is the extra. The track is
    hidden from screen readers, which reach the columns through the table.

    State: resources/js/components/table-scroll-rail.js. Styles: filament-table.css.
--}}
<div
    class="ui-table-scroll"
    x-data="tableScrollRail(@js(__('admin.scrollable_table')))"
    x-bind:class="{ 'is-stuck': stuck, 'is-overflowing': overflows, 'is-at-end': atEnd }"
    x-bind:style="{ '--ui-table-content-top': `${contentTop}px`, '--ui-table-content-height': `${contentHeight}px`, '--ui-table-footer-height': `${footerHeight}px` }"
>
    <div class="ui-table-fade ui-table-fade-start" x-bind:class="{ 'is-visible': overflows && ! atStart }"></div>
    <div class="ui-table-fade ui-table-fade-end" x-bind:class="{ 'is-visible': overflows && ! atEnd }"></div>

    <div class="ui-table-rail" x-ref="rail" x-show="overflows" style="display: none;">
        <x-button.icon
            class="ui-table-rail-arrow"
            size="xs"
            :aria-label="__('admin.scroll_table_left')"
            x-bind:aria-disabled="atStart.toString()"
            x-on:click="scrollByPage(-1)"
        >
            <x-heroicon-o-chevron-left class="h-4 w-4" aria-hidden="true" />
        </x-button.icon>

        <div class="ui-table-rail-track" aria-hidden="true" x-ref="track" x-on:pointerdown="startDrag($event)">
            <div class="ui-table-rail-thumb" x-bind:style="{ width: `${thumbWidth}%`, left: `${thumbLeft}%` }"></div>
        </div>

        <x-button.icon
            class="ui-table-rail-arrow"
            size="xs"
            :aria-label="__('admin.scroll_table_right')"
            x-bind:aria-disabled="atEnd.toString()"
            x-on:click="scrollByPage(1)"
        >
            <x-heroicon-o-chevron-right class="h-4 w-4" aria-hidden="true" />
        </x-button.icon>
    </div>
</div>
