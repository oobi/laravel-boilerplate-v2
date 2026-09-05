<div>
    @section('page-title', __('admin.create'))

    <x-page-header :title="__('admin.add_role')" />

    <form wire:submit="create">
        {{ $this->form }}

        <div class="mt-6 flex items-center gap-4">
            <x-filament::button type="submit">
                {{ __('admin.add_role') }}
            </x-filament::button>

            <x-button color="gray" variant="ghost" href="{{ route('roles.index') }}">
                {{ __('admin.cancel') }}
            </x-button>
        </div>
    </form>
</div>
