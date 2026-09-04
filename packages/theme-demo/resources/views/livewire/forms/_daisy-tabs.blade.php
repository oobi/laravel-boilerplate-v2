<x-tabs-item :active="$variant === 'floating'" href="{{ route('style-demo.forms-daisy') }}" icon="heroicon-o-square-2-stack">
    {{ __('theme-demo::messages.forms_daisy_tab_floating') }}
</x-tabs-item>

<x-tabs-item :active="$variant === 'standard'" href="{{ route('style-demo.forms-daisy-standard') }}" icon="heroicon-o-bars-3-bottom-left">
    {{ __('theme-demo::messages.forms_daisy_tab_standard') }}
</x-tabs-item>
