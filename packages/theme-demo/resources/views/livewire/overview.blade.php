@section('page-title', __('theme-demo::messages.overview_title'))

<div class="flex flex-col gap-4">
    <x-page-header :title="__('theme-demo::messages.overview_title')" :description="__('theme-demo::messages.overview_description')" />

    <x-banner variant="info">
        {{ __('theme-demo::messages.overview_description') }}
    </x-banner>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach ([
            ['route' => 'style-demo.tables-empty', 'label' => __('theme-demo::messages.nav_tables'), 'description' => __('theme-demo::messages.tables_description')],
            ['route' => 'style-demo.tables-filament-empty', 'label' => __('theme-demo::messages.nav_filament_table'), 'description' => __('theme-demo::messages.filament_table_description')],
            ['route' => 'style-demo.forms-daisy', 'label' => __('theme-demo::messages.nav_forms_daisy'), 'description' => __('theme-demo::messages.forms_daisy_description')],
            ['route' => 'style-demo.forms-filament', 'label' => __('theme-demo::messages.nav_filament_form'), 'description' => __('theme-demo::messages.filament_form_description')],
            ['route' => 'style-demo.components', 'label' => __('theme-demo::messages.nav_components'), 'description' => __('theme-demo::messages.components_description')],
            ['route' => 'style-demo.tab-content-table', 'label' => __('theme-demo::messages.nav_tab_content'), 'description' => __('theme-demo::messages.tab_content_description')],
        ] as $link)
            <a href="{{ route($link['route']) }}" class="card bg-base-100 border border-base-300 transition hover:border-primary">
                <div class="card-body">
                    <h2 class="card-title">{{ $link['label'] }}</h2>
                    <p class="text-sm text-base-content/70">{{ $link['description'] }}</p>
                </div>
            </a>
        @endforeach
    </div>

    <div class="divider"></div>

    <div class="flex flex-col gap-2">
        <h2 class="text-lg font-semibold">{{ __('theme-demo::messages.error_pages_title') }}</h2>
        <p class="text-sm text-base-content/70">{{ __('theme-demo::messages.error_pages_description') }}</p>

        <div class="flex flex-wrap gap-2">
            @foreach (['403', '404', '500', '503'] as $status)
                {{-- Opens in a new tab: the target view has no admin chrome, so this avoids losing the demo page. --}}
                <a href="{{ route('style-demo.errors-'.$status) }}" target="_blank" rel="noopener" class="btn btn-outline btn-sm">
                    {{ $status }}
                </a>
            @endforeach
        </div>
    </div>
</div>
