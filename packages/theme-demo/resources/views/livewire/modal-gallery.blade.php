@section('page-title', __('theme-demo::messages.modals_daisy_title'))

<div class="flex flex-col gap-6">
    <x-page-header :title="__('theme-demo::messages.modals_daisy_title')" :description="__('theme-demo::messages.modals_daisy_description')" />

    {{-- Semantic buttons --}}
    <x-card :title="__('theme-demo::messages.modals_daisy_buttons_heading')" bodyClass="gap-4">
        <p class="ui-subtle text-sm">{{ __('theme-demo::messages.modals_daisy_buttons_hint') }}</p>

        <div class="flex flex-wrap items-center gap-3">
            <x-button.action>{{ __('admin.save_changes') }}</x-button.action>
            <x-button.warning>{{ __('admin.impersonate_user') }}</x-button.warning>
            <x-button.danger>{{ __('admin.delete') }}</x-button.danger>
            <x-button.cancel />
        </div>
    </x-card>

    {{-- Confirmation modals, one per flavour --}}
    <x-card :title="__('theme-demo::messages.modals_daisy_confirm_heading')" bodyClass="gap-4">
        <p class="ui-subtle text-sm">{{ __('theme-demo::messages.modals_daisy_confirm_hint') }}</p>

        <div class="flex flex-wrap gap-3">
            <x-button.danger x-on:click="$wire.showDelete = true">{{ __('theme-demo::messages.modals_daisy_delete_trigger') }}</x-button.danger>
            <x-button.warning x-on:click="$wire.showImpersonate = true">{{ __('theme-demo::messages.modals_daisy_impersonate_trigger') }}</x-button.warning>
            <x-button.action x-on:click="$wire.showInfo = true">{{ __('theme-demo::messages.modals_daisy_info_trigger') }}</x-button.action>
            <x-button.action x-on:click="$wire.showSuccess = true">{{ __('theme-demo::messages.modals_daisy_success_trigger') }}</x-button.action>
        </div>
    </x-card>

    {{-- Modal with a form --}}
    <x-card :title="__('theme-demo::messages.modals_daisy_form_heading')" bodyClass="gap-4">
        <p class="ui-subtle text-sm">{{ __('theme-demo::messages.modals_daisy_form_hint') }}</p>

        <div>
            <x-button.action x-on:click="$wire.showArchive = true">{{ __('theme-demo::messages.modals_daisy_form_trigger') }}</x-button.action>
        </div>
    </x-card>

    {{-- Password ("sudo") confirmation --}}
    <x-card :title="__('theme-demo::messages.modals_daisy_sudo_heading')" bodyClass="gap-4">
        <p class="ui-subtle text-sm">{{ __('theme-demo::messages.modals_daisy_hint') }}</p>

        <div class="flex flex-wrap gap-3">
            <x-button.action wire:click="confirmDemo('default')">{{ __('theme-demo::messages.modals_daisy_trigger_default') }}</x-button.action>
            <x-button.danger wire:click="confirmDemo('custom')">{{ __('theme-demo::messages.modals_daisy_trigger_custom') }}</x-button.danger>
        </div>
    </x-card>

    {{-- Modal instances --}}
    <x-confirm-modal
        wire:model="showDelete"
        variant="danger"
        :title="__('theme-demo::messages.modals_daisy_delete_title')"
        :description="__('theme-demo::messages.modals_daisy_delete_description')"
        confirm="deleteRecord"
        :confirm-label="__('theme-demo::messages.modals_daisy_delete_submit')"
    />

    <x-confirm-modal
        wire:model="showImpersonate"
        variant="warning"
        :title="__('theme-demo::messages.modals_daisy_impersonate_title')"
        :description="__('theme-demo::messages.modals_daisy_impersonate_description')"
        confirm="impersonate"
        :confirm-label="__('theme-demo::messages.modals_daisy_impersonate_submit')"
    />

    <x-confirm-modal
        wire:model="showInfo"
        variant="info"
        :title="__('theme-demo::messages.modals_daisy_info_title')"
        :description="__('theme-demo::messages.modals_daisy_info_description')"
        confirm="acknowledge"
    />

    <x-confirm-modal
        wire:model="showSuccess"
        variant="success"
        :title="__('theme-demo::messages.modals_daisy_success_title')"
        :description="__('theme-demo::messages.modals_daisy_success_description')"
        confirm="acknowledge"
    />

    <x-modal
        wire:model="showArchive"
        variant="info"
        :title="__('theme-demo::messages.modals_daisy_form_title')"
        submit="archive"
    >
        <x-form-input name="reason" :label="__('theme-demo::messages.modals_daisy_form_reason')" wire:model="reason" required />

        <x-slot:footer>
            <x-button.cancel x-on:click="open = false" />
            <x-button.action type="submit">{{ __('theme-demo::messages.modals_daisy_form_submit') }}</x-button.action>
        </x-slot:footer>
    </x-modal>

    {{-- One sudo modal instance; the "custom" trigger swaps in overridden copy. --}}
    <x-confirm-password-modal
        :title="$confirmingAction === 'custom' ? __('theme-demo::messages.modals_daisy_custom_title') : null"
        :description="$confirmingAction === 'custom' ? __('theme-demo::messages.modals_daisy_custom_description') : null"
    />
</div>
