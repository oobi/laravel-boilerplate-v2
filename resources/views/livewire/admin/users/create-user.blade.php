<div>
    @section('page-title', __('admin.create'))

    <x-page-header :title="__('admin.add_user')" />

    <form wire:submit="create">
        {{ $this->form }}

        <div class="mt-6 flex items-center gap-4">
            <x-button.action type="submit">
                {{ __('admin.add_user') }}
            </x-button.action>

            <x-button.cancel href="{{ route('users.index') }}" />
        </div>
    </form>
</div>
