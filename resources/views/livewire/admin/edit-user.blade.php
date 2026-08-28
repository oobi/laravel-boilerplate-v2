<div>
    @section('page-title', __('admin.edit_user'))

    <div class="ui-page-header">
        <h1 class="ui-page-title flex items-center gap-2">
            {{ __('admin.edit_user') }}
            @if ($user->id === auth()->id())
                <x-badge color="success">{{ __('admin.you') }}</x-badge>
            @endif
        </h1>

        <div class="ui-page-actions">
            {{ $this->resetPasswordAction }}
        </div>
    </div>

    <form wire:submit="save">
        {{ $this->form }}

        <div class="mt-6 flex items-center gap-4">
            <x-filament::button type="submit">
                {{ __('admin.save_changes') }}
            </x-filament::button>

            <x-filament::button color="gray" tag="a" href="{{ route('users.index') }}">
                {{ __('admin.cancel') }}
            </x-filament::button>
        </div>
    </form>

    <x-filament-actions::modals />
</div>
