{{-- Shared tab items for the three table demo pages — included inside each page's <x-tabs.nav>. --}}
<x-tabs.item
    :active="request()->routeIs('style-demo.tables-empty')"
    href="{{ route('style-demo.tables-empty') }}"
    icon="heroicon-o-inbox"
>
    {{ __('theme-demo::messages.tables_tab_empty') }}
</x-tabs.item>

<x-tabs.item
    :active="request()->routeIs('style-demo.tables-simple')"
    href="{{ route('style-demo.tables-simple') }}"
    icon="heroicon-o-table-cells"
>
    {{ __('theme-demo::messages.tables_tab_simple') }}
</x-tabs.item>

<x-tabs.item
    :active="request()->routeIs('style-demo.tables-maximalist')"
    href="{{ route('style-demo.tables-maximalist') }}"
    icon="heroicon-o-squares-2x2"
    :badge="\Concise\ThemeDemo\Support\DemoRows::all()->count()"
>
    {{ __('theme-demo::messages.tables_tab_maximalist') }}
</x-tabs.item>

<x-tabs.item
    :active="request()->routeIs('style-demo.tables-wide')"
    href="{{ route('style-demo.tables-wide') }}"
    icon="heroicon-o-arrows-right-left"
>
    {{ __('theme-demo::messages.tables_tab_wide') }}
</x-tabs.item>
