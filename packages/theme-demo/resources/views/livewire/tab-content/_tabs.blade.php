<x-tabs-item :active="$variant === 'table'" href="{{ route('style-demo.tab-content-table') }}" icon="heroicon-o-table-cells">
    {{ __('theme-demo::messages.tab_content_tab_table') }}
</x-tabs-item>

<x-tabs-item :active="$variant === 'form'" href="{{ route('style-demo.tab-content-form') }}" icon="heroicon-o-pencil-square">
    {{ __('theme-demo::messages.tab_content_tab_form') }}
</x-tabs-item>

<x-tabs-item :active="$variant === 'panels'" href="{{ route('style-demo.tab-content-panels') }}" icon="heroicon-o-squares-2x2">
    {{ __('theme-demo::messages.tab_content_tab_panels') }}
</x-tabs-item>

<x-tabs-item :active="$variant === 'text'" href="{{ route('style-demo.tab-content-text') }}" icon="heroicon-o-document-text">
    {{ __('theme-demo::messages.tab_content_tab_text') }}
</x-tabs-item>
