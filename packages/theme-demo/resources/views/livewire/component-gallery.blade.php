@section('page-title', __('theme-demo::messages.components_title'))

<div class="flex flex-col gap-6">
    <x-page-header :title="__('theme-demo::messages.components_title')" :description="__('theme-demo::messages.components_description')" />

    {{-- Alerts (flash-style messages) --}}
    <div class="card bg-base-100 border border-base-300">
        <div class="card-body gap-3">
            <h2 class="card-title">{{ __('Alerts') }}</h2>
            <div class="alert alert-success">{{ __('Success message.') }}</div>
            <div class="alert alert-error">{{ __('Error message.') }}</div>
            <div class="alert alert-warning">{{ __('Warning message.') }}</div>
            <div class="alert alert-info">{{ __('Info message.') }}</div>
        </div>
    </div>

    {{-- Banners --}}
    <div class="card bg-base-100 border border-base-300">
        <div class="card-body gap-3">
            <h2 class="card-title">{{ __('Banners') }}</h2>
            @foreach (['info', 'success', 'warning', 'error'] as $variant)
                <x-banner :variant="$variant" dismissible>
                    {{ ucfirst($variant) }} banner — persistent, contextual messaging.
                </x-banner>
            @endforeach
        </div>
    </div>

    {{-- Buttons --}}
    <div class="card bg-base-100 border border-base-300">
        <div class="card-body gap-4">
            <h2 class="card-title">{{ __('Buttons') }}</h2>
            @foreach ($buttonVariants as $variant)
                <div>
                    <div class="ui-subtle mb-1">{{ ucfirst($variant) }}</div>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($colors as $color)
                            <button type="button" class="btn btn-sm {{ $variant === 'solid' ? "btn-{$color}" : "btn-{$variant} btn-{$color}" }}">
                                {{ ucfirst($color) }}
                            </button>
                        @endforeach
                    </div>
                </div>
            @endforeach

            <div>
                <div class="ui-subtle mb-1">{{ __('Button group') }}</div>
                <div class="join">
                    <button type="button" class="join-item btn btn-sm btn-active">{{ __('All') }}</button>
                    <button type="button" class="join-item btn btn-sm">{{ __('Active') }}</button>
                    <button type="button" class="join-item btn btn-sm">{{ __('Archived') }}</button>
                </div>
            </div>

            <div>
                <div class="ui-subtle mb-1">{{ __('Sizes') }}</div>
                <div class="flex flex-wrap items-center gap-2">
                    @foreach ($sizes as $size)
                        <button type="button" class="btn btn-primary btn-{{ $size }}">{{ strtoupper($size) }}</button>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- Badges --}}
    <div class="card bg-base-100 border border-base-300">
        <div class="card-body gap-3">
            <h2 class="card-title">{{ __('Badges') }}</h2>
            @foreach (['solid', 'soft', 'outline', 'ghost'] as $variant)
                <div>
                    <div class="ui-subtle mb-1">{{ ucfirst($variant) }}</div>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($colors as $color)
                            <x-badge :color="$color" :variant="$variant">{{ ucfirst($color) }}</x-badge>
                        @endforeach
                    </div>
                </div>
            @endforeach

            <div>
                <div class="ui-subtle mb-1">{{ __('Sizes') }}</div>
                <div class="flex flex-wrap items-center gap-2">
                    @foreach ($sizes as $size)
                        <x-badge color="primary" :size="$size">{{ strtoupper($size) }}</x-badge>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- Avatars --}}
    <div class="card bg-base-100 border border-base-300">
        <div class="card-body gap-3">
            <h2 class="card-title">{{ __('Avatars') }}</h2>
            @foreach (['solid', 'soft', 'ghost', 'outline'] as $variant)
                <div>
                    <div class="ui-subtle mb-1">{{ ucfirst($variant) }}</div>
                    <div class="flex flex-wrap items-center gap-2">
                        @foreach ($colors as $color)
                            <x-avatar name="{{ ucfirst($color) }} Demo" :color="$color" :variant="$variant" />
                        @endforeach
                    </div>
                </div>
            @endforeach

            <div>
                <div class="ui-subtle mb-1">{{ __('Sizes') }}</div>
                <div class="flex flex-wrap items-center gap-2">
                    @foreach (['xs', 'sm', 'md', 'lg', 'xl'] as $size)
                        <x-avatar name="Jane Doe" :size="$size" />
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- Stats cards --}}
    <div class="card bg-base-100 border border-base-300">
        <div class="card-body gap-3">
            <h2 class="card-title">{{ __('Stats cards') }}</h2>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <x-stats-card label="Total users" value="1,204" color="primary" />
                <x-stats-card label="Active today" value="87" color="success" />
                <x-stats-card label="Pending review" value="12" color="warning" />
            </div>
        </div>
    </div>

    {{-- Color matrix — this app has no shade-ramp utilities (see theme/components/ui/colors.css), just solid + soft per semantic color --}}
    <div class="card bg-base-100 border border-base-300">
        <div class="card-body gap-3">
            <h2 class="card-title">{{ __('Color palette') }}</h2>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($colors as $color)
                    <div class="flex flex-col gap-1">
                        <div class="ui-subtle">{{ ucfirst($color) }}</div>
                        <div class="flex h-10 items-center justify-center rounded-box bg-{{ $color }} text-{{ $color }}-content text-xs">{{ __('solid') }}</div>
                        <div class="flex h-10 items-center justify-center rounded-box bg-soft-{{ $color }} text-{{ $color }} text-xs">{{ __('soft') }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
