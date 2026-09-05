<div>
    @section('page-title', __('admin.edit'))

    <x-page-header :title="__('admin.edit_role')" />

    <form wire:submit="save">
        {{ $this->form }}

        <div class="mt-6 flex items-center gap-4">
            <x-filament::button type="submit">
                {{ __('admin.save_changes') }}
            </x-filament::button>

            <x-button color="gray" variant="ghost" href="{{ route('roles.index') }}">
                {{ __('admin.cancel') }}
            </x-button>
        </div>
    </form>
</div>
