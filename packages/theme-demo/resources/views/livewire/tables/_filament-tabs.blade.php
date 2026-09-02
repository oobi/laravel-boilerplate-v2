{{-- Shared tab items for the four Filament table demo variants — included inside the page's <x-tabs-nav>. --}}
<x-tabs-item
    :active="$variant === 'empty'"
    href="{{ route('style-demo.tables-filament-empty') }}"
    icon="heroicon-o-inbox"
>
    {{ __('theme-demo::messages.tables_tab_empty') }}
</x-tabs-item>

<x-tabs-item
    :active="$variant === 'simple'"
    href="{{ route('style-demo.tables-filament-simple') }}"
    icon="heroicon-o-table-cells"
>
    {{ __('theme-demo::messages.tables_tab_simple') }}
</x-tabs-item>

<x-tabs-item
    :active="$variant === 'maximalist'"
    href="{{ route('style-demo.tables-filament-maximalist') }}"
    icon="heroicon-o-squares-2x2"
    :badge="\Concise\ThemeDemo\Support\DemoRows::all()->count()"
>
    {{ __('theme-demo::messages.tables_tab_maximalist') }}
</x-tabs-item>

<x-tabs-item
    :active="$variant === 'custom-header'"
    href="{{ route('style-demo.tables-filament-custom-header') }}"
    icon="heroicon-o-adjustments-horizontal"
>
    {{ __('theme-demo::messages.tables_tab_custom_header') }}
</x-tabs-item>

<x-tabs-item
    :active="$variant === 'wide'"
    href="{{ route('style-demo.tables-filament-wide') }}"
    icon="heroicon-o-arrows-right-left"
>
    {{ __('theme-demo::messages.tables_tab_wide') }}
</x-tabs-item>
