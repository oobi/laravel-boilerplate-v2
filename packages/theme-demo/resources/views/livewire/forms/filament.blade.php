@section('page-title', __('theme-demo::messages.filament_form_title'))

<div class="flex flex-col gap-4">
    <x-page-header :title="__('theme-demo::messages.filament_form_title')" :description="__('theme-demo::messages.filament_form_description')" />

    <form wire:submit="submit">
        <x-card>
            {{ $this->form }}

            <x-slot:footer class="justify-end">
                <x-button.action type="submit">{{ __('Submit') }}</x-button.action>
            </x-slot:footer>
        </x-card>
    </form>
</div>
