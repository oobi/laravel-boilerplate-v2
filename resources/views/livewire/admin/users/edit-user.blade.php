<div>
    @section('page-title', __('admin.edit'))

    <x-page-header>
        <x-slot:title>
            <span class="flex items-center gap-2">
                {{ __('admin.edit_user') }}
                @if ($user->id === auth()->id())
                    <x-badge color="success">{{ __('admin.you') }}</x-badge>
                @endif
            </span>
        </x-slot:title>
    </x-page-header>

    <form wire:submit="save">
        {{ $this->form }}

        <div class="mt-6 flex items-center gap-4">
            <x-button.action type="submit">
                {{ __('admin.save_changes') }}
            </x-button.action>

            <x-button.cancel href="{{ route('users.show', $user) }}" />
        </div>
    </form>
</div>
