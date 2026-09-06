@section('page-title', __('theme-demo::messages.modals_daisy_title'))

<div class="flex flex-col gap-6">
    <x-page-header :title="__('theme-demo::messages.modals_daisy_title')" :description="__('theme-demo::messages.modals_daisy_description')" />

    {{-- Password ("sudo") confirmation --}}
    <x-card :title="__('Password confirmation')" bodyClass="gap-4">
        <p class="ui-subtle text-sm">
            {{ __('theme-demo::messages.modals_daisy_hint') }}
        </p>

        <div class="flex flex-wrap gap-2">
            <x-button color="primary" wire:click="confirmDemo('default')">
                {{ __('theme-demo::messages.modals_daisy_trigger_default') }}
            </x-button>

            <x-button color="error" wire:click="confirmDemo('custom')">
                {{ __('theme-demo::messages.modals_daisy_trigger_custom') }}
            </x-button>
        </div>
    </x-card>

    {{-- One modal instance; the "custom" trigger swaps in overridden copy. --}}
    <x-confirm-password-modal
        :show="$confirmingPassword"
        :title="$confirmingAction === 'custom' ? __('theme-demo::messages.modals_daisy_custom_title') : null"
        :description="$confirmingAction === 'custom' ? __('theme-demo::messages.modals_daisy_custom_description') : null"
    />
</div>
