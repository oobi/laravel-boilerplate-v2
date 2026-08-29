@section('page-title', __('theme-demo::messages.overview_title'))

<div class="flex flex-col gap-4">
    <x-page-header :title="__('theme-demo::messages.overview_title')" :description="__('theme-demo::messages.overview_description')" />

    <x-banner variant="info">
        {{ __('theme-demo::messages.overview_description') }}
    </x-banner>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach ([
            ['route' => 'style-demo.tables-empty', 'label' => __('theme-demo::messages.nav_tables'), 'description' => __('theme-demo::messages.tables_description')],
            ['route' => 'style-demo.forms-daisy', 'label' => __('theme-demo::messages.nav_forms_daisy'), 'description' => __('theme-demo::messages.forms_daisy_description')],
            ['route' => 'style-demo.components', 'label' => __('theme-demo::messages.nav_components'), 'description' => __('theme-demo::messages.components_description')],
        ] as $link)
            <a href="{{ route($link['route']) }}" class="card bg-base-100 border border-base-300 transition hover:border-primary">
                <div class="card-body">
                    <h2 class="card-title">{{ $link['label'] }}</h2>
                    <p class="text-sm text-base-content/70">{{ $link['description'] }}</p>
                </div>
            </a>
        @endforeach
    </div>
</div>
