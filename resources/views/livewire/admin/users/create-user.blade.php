<div>
    @section('page-title', __('admin.create'))

    <x-page-header :title="__('admin.add_user')" />

    <form wire:submit="create">
        {{ $this->form }}

        <x-form-actions>
            <x-button.action type="submit">
                {{ __('admin.add_user') }}
            </x-button.action>

            <x-button.cancel href="{{ route('users.index') }}" />
        </x-form-actions>
    </form>
</div>
