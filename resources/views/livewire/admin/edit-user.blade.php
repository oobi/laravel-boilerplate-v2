<div>
    @section('page-title', __('admin.edit'))

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

            <x-button color="gray" variant="ghost" href="{{ route('users.show', $user) }}">
                {{ __('admin.cancel') }}
            </x-button>
        </div>
    </form>

    <x-filament-actions::modals />
</div>
