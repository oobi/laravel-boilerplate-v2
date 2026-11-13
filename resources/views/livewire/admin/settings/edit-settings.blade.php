<div>
    @section('page-title', __('settings.title'))

    <x-page-header :title="__('settings.title')" :description="__('settings.description')" />

    <form wire:submit="save">
        {{ $this->form }}

        <x-form-actions>
            <x-button.action type="submit">
                {{ __('admin.save_changes') }}
            </x-button.action>
        </x-form-actions>
    </form>
</div>
