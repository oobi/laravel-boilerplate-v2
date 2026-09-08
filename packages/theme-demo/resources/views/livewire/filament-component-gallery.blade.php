@section('page-title', __('theme-demo::messages.filament_components_title'))

<div class="flex flex-col gap-6">
    <x-page-header :title="__('theme-demo::messages.filament_components_title')" :description="__('theme-demo::messages.filament_components_description')" />

    {{-- Buttons --}}
    <x-card :title="__('Buttons')" bodyClass="gap-4">
        {{-- Solid + Outlined are Filament's own; Soft is this app's addition
             (fi-btn-soft, filament-buttons.css / AdminAction::soft()). --}}
        @foreach (['Solid' => '', 'Soft' => 'fi-btn-soft', 'Outlined' => 'outlined'] as $variant => $mod)
            <div>
                <div class="ui-subtle mb-1">{{ $variant }}</div>
                <div class="flex flex-wrap gap-2">
                    @foreach ($filamentColors as $label => $color)
                        <x-filament::button
                            :color="$color"
                            :outlined="$mod === 'outlined'"
                            size="sm"
                            @class(['fi-btn-soft' => $mod === 'fi-btn-soft'])
                        >
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
    </x-card>

    {{-- Badges. No size row: unlike buttons, Filament badges ship no lg/xl
         styles and this app normalises them to one box (see filament-badge.css,
         which pins table-column badges to the <x-badge> size) — so a size scale
         would misrepresent them. --}}
    <x-card :title="__('Badges')">
        <div class="flex flex-wrap gap-2">
            @foreach ($filamentColors as $label => $color)
                <x-filament::badge :color="$color">{{ ucfirst($label) }}</x-filament::badge>
            @endforeach
        </div>
    </x-card>

    {{-- Icon buttons --}}
    <x-card :title="__('Icon buttons')">
        <div class="flex flex-wrap items-center gap-2">
            @foreach ($filamentColors as $label => $color)
                <x-filament::icon-button :color="$color" icon="heroicon-o-pencil-square" :tooltip="ucfirst($label)" />
            @endforeach
        </div>
    </x-card>

    {{-- Notifications --}}
    <x-card :title="__('Notifications')">
        <div class="flex flex-wrap gap-2">
            <x-filament::button color="success" wire:click="sendNotification('success')">{{ __('Success') }}</x-filament::button>
            <x-filament::button color="danger" wire:click="sendNotification('danger')">{{ __('Danger') }}</x-filament::button>
            <x-filament::button color="warning" wire:click="sendNotification('warning')">{{ __('Warning') }}</x-filament::button>
            <x-filament::button color="info" wire:click="sendNotification('info')">{{ __('Info') }}</x-filament::button>
        </div>
    </x-card>
</div>
