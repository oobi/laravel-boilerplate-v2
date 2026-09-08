@section('page-title', __('theme-demo::messages.components_title'))

<div class="flex flex-col gap-6">
    <x-page-header :title="__('theme-demo::messages.components_title')" :description="__('theme-demo::messages.components_description')" />

    {{-- Alerts (flash-style messages) --}}
    <x-card :title="__('Alerts')">
        <x-alert color="success">{{ __('Success message.') }}</x-alert>
        <x-alert color="error">{{ __('Error message.') }}</x-alert>
        <x-alert color="warning">{{ __('Warning message.') }}</x-alert>
        <x-alert color="info">{{ __('Info message.') }}</x-alert>
    </x-card>

    {{-- Banners --}}
    <x-card :title="__('Banners')">
        @foreach (['info', 'success', 'warning', 'error'] as $variant)
            <x-banner :variant="$variant" dismissible>
                {{ ucfirst($variant) }} banner — persistent, contextual messaging.
            </x-banner>
        @endforeach
    </x-card>

    {{-- Buttons --}}
    <x-card :title="__('Buttons')" bodyClass="gap-4">
        {{-- Semantic intent components — the preferred API in app markup (see .ai/rules/components.md) --}}
        <div>
            <div class="ui-subtle mb-1">{{ __('Semantic') }}</div>
            <p class="ui-subtle mb-2 text-sm">{{ __('theme-demo::messages.components_buttons_semantic_hint') }}</p>
            <div class="flex flex-wrap items-center gap-2">
                <x-button.action>{{ __('Action') }}</x-button.action>
                <x-button.secondary>{{ __('Secondary') }}</x-button.secondary>
                <x-button.cancel />
                <x-button.back href="#" />
                <x-button.warning>{{ __('Warning') }}</x-button.warning>
                <x-button.danger>{{ __('Danger') }}</x-button.danger>
                <x-button.icon aria-label="{{ __('Open menu') }}">
                    <x-heroicon-o-bars-3 class="h-5 w-5" />
                </x-button.icon>
                <x-button.icon circle aria-label="{{ __('admin.close') }}">
                    <x-heroicon-o-x-mark class="h-5 w-5" />
                </x-button.icon>
            </div>
        </div>

        {{-- Primitive: raw colour × variant matrix behind the semantic components --}}
        <div class="ui-subtle mb-1 border-t border-base-300 pt-4">{{ __('Primitive') }} (&lt;x-button&gt;)</div>
        @foreach ($buttonVariants as $variant)
            <div>
                <div class="ui-subtle mb-1">{{ ucfirst($variant) }}</div>
                <div class="flex flex-wrap gap-2">
                    @foreach ($colors as $color)
                        <x-button :color="$color" :variant="$variant" size="sm">{{ ucfirst($color) }}</x-button>
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
                    <x-button color="primary" :size="$size">{{ strtoupper($size) }}</x-button>
                @endforeach
            </div>
        </div>
    </x-card>

    {{-- Badges --}}
    <x-card :title="__('Badges')">
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
    </x-card>

    {{-- Avatars --}}
    <x-card :title="__('Avatars')">
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
    </x-card>

    {{-- Stats cards --}}
    <x-card :title="__('Stats cards')">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <x-stats-card label="Total users" value="1,204" color="primary" />
            <x-stats-card label="Active today" value="87" color="success" />
            <x-stats-card label="Pending review" value="12" color="warning" />
        </div>
    </x-card>

    {{-- Color matrix — this app has no shade-ramp utilities (see theme/components/ui/colors.css), just solid + soft per semantic color --}}
    <x-card :title="__('Color palette')">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($colors as $color)
                <div class="flex flex-col gap-1">
                    <div class="ui-subtle">{{ ucfirst($color) }}</div>
                    <div class="flex h-10 items-center justify-center rounded-box bg-{{ $color }} text-{{ $color }}-content text-xs">{{ __('solid') }}</div>
                    <div class="flex h-10 items-center justify-center rounded-box bg-soft-{{ $color }} text-{{ $color }} text-xs">{{ __('soft') }}</div>
                </div>
            @endforeach
        </div>
    </x-card>
</div>
