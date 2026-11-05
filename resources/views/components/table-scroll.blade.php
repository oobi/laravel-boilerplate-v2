{{--
    <x-table-scroll>: wraps a hand-built daisyUI <table class="table"> so it
    behaves like a Filament table: from lg up the column header stays in view, and while
    the table is wider than its space it shows the scroll rail and edge fades
    (filament/tables/scroll-rail). The table scrolls sideways inside it, and
    keyboard users can scroll it too.

    A <th class="ui-table-actions"> as the last header cell freezes that column,
    so a row menu stays in reach. A last cell with a colspan (a detail row) isn't
    frozen. A row with a tint sets --ui-row-bg on its <tr>, so the frozen cell
    matches. For now an <x-dropdown> in here is cut off on the last rows, even
    when the table fits.

    Slots:
    - default: the <table>
    - footer: the pagination, which sticks from sm up on a tall enough screen

    Per-table classes, as for a Filament table: ui-sticky-footer-phone,
    ui-no-sticky-footer-desktop, ui-no-sticky-header-desktop,
    ui-no-rail-arrows-phone, ui-no-rail-arrows-desktop.
--}}
<div {{ $attributes->class('ui-table-main') }}>
    <div class="ui-table-scroller">
        {{ $slot }}
    </div>

    @include('filament.tables.scroll-rail')

    {{ $footer ?? '' }}
</div>
