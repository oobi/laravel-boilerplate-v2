<div>
    @section('page-title', __('admin.create'))

    <x-page-header :title="__('admin.add_role')" :description="$description" />

    <form wire:submit="create">
        {{ $this->form }}

        <x-form-actions>
            <x-button.action type="submit">
                {{ __('admin.add_role') }}
            </x-button.action>

            <x-button.cancel href="{{ $cancelUrl }}" />
        </x-form-actions>
    </form>
</div>
