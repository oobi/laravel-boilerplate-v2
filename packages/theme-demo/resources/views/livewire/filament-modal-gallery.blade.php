@section('page-title', __('theme-demo::messages.modals_filament_title'))

<div class="flex flex-col gap-6">
    <x-page-header :title="__('theme-demo::messages.modals_filament_title')" :description="__('theme-demo::messages.modals_filament_description')" />

    {{-- Confirmation --}}
    <x-card :title="__('Confirmation')" bodyClass="gap-2">
        <p class="ui-subtle text-sm">{{ __('theme-demo::messages.modals_filament_confirm_hint') }}</p>
        <div>{{ $this->confirmAction }}</div>
    </x-card>

    {{-- Destructive, with custom copy --}}
    <x-card :title="__('Destructive confirmation')" bodyClass="gap-2">
        <p class="ui-subtle text-sm">{{ __('theme-demo::messages.modals_filament_destroy_hint') }}</p>
        <div>{{ $this->destroyAction }}</div>
    </x-card>

    {{-- Modal with a form schema --}}
    <x-card :title="__('Modal with a form')" bodyClass="gap-2">
        <p class="ui-subtle text-sm">{{ __('theme-demo::messages.modals_filament_form_hint') }}</p>
        <div>{{ $this->formModalAction }}</div>
    </x-card>

    <x-filament-actions::modals />
</div>
