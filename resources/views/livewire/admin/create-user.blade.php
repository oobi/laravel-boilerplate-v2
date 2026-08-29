<div>
    @section('page-title', __('admin.create'))

    <div class="ui-page-header">
        <h1 class="ui-page-title">{{ __('admin.add_user') }}</h1>
    </div>

    <form wire:submit="create">
        {{ $this->form }}

        <div class="mt-6 flex items-center gap-4">
            <x-filament::button type="submit">
                {{ __('admin.add_user') }}
            </x-filament::button>

            <x-filament::button color="gray" tag="a" href="{{ route('users.index') }}">
                {{ __('admin.cancel') }}
            </x-filament::button>
        </div>
    </form>
</div>
