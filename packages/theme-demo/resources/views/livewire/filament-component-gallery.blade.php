@section('page-title', __('theme-demo::messages.filament_components_title'))

<div class="flex flex-col gap-6">
    <x-page-header :title="__('theme-demo::messages.filament_components_title')" :description="__('theme-demo::messages.filament_components_description')" />

    {{-- Buttons --}}
    <div class="card bg-base-100 border border-base-300">
        <div class="card-body gap-4">
            <h2 class="card-title">{{ __('Buttons') }}</h2>
            @foreach (['solid' => false, 'outlined' => true] as $variant => $outlined)
                <div>
                    <div class="ui-subtle mb-1">{{ ucfirst($variant) }}</div>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($filamentColors as $label => $color)
                            <x-filament::button :color="$color" :outlined="$outlined" size="sm">
                                {{ ucfirst($label) }}
                            </x-filament::button>
                        @endforeach
                    </div>
                </div>
            @endforeach

            <div>
                <div class="ui-subtle mb-1">{{ __('Sizes') }}</div>
                <div class="flex flex-wrap items-center gap-2">
                    @foreach ($sizes as $size)
                        <x-filament::button color="primary" :size="$size">{{ strtoupper($size) }}</x-filament::button>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- Badges --}}
    <div class="card bg-base-100 border border-base-300">
        <div class="card-body gap-3">
            <h2 class="card-title">{{ __('Badges') }}</h2>
            <div class="flex flex-wrap gap-2">
                @foreach ($filamentColors as $label => $color)
                    <x-filament::badge :color="$color">{{ ucfirst($label) }}</x-filament::badge>
                @endforeach
            </div>

            <div>
                <div class="ui-subtle mb-1">{{ __('Sizes') }}</div>
                <div class="flex flex-wrap items-center gap-2">
                    @foreach ($sizes as $size)
                        <x-filament::badge color="primary" :size="$size">{{ strtoupper($size) }}</x-filament::badge>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- Icon buttons --}}
    <div class="card bg-base-100 border border-base-300">
        <div class="card-body gap-3">
            <h2 class="card-title">{{ __('Icon buttons') }}</h2>
            <div class="flex flex-wrap items-center gap-2">
                @foreach ($filamentColors as $label => $color)
                    <x-filament::icon-button :color="$color" icon="heroicon-o-pencil-square" :tooltip="ucfirst($label)" />
                @endforeach
            </div>
        </div>
    </div>
</div>
