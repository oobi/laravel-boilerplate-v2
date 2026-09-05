<div>
    @section('page-title', __('admin.create'))

    <x-page-header :title="__('admin.add_user')" />

    <form wire:submit="create">
        {{ $this->form }}

        <div class="mt-6 flex items-center gap-4">
            <x-filament::button type="submit">
                {{ __('admin.add_user') }}
            </x-filament::button>

            <x-button color="gray" variant="ghost" href="{{ route('users.index') }}">
                {{ __('admin.cancel') }}
            </x-button>
        </div>
    </form>
</div>
