<div>
    @section('page-parent', $user->name)
    @section('page-parent-url', route('users.show', $user))
    @section('page-title', __('admin.edit'))

    <x-page-header>
        <x-slot:title>
            {{ __('admin.edit_user') }}
            @if ($user->id === auth()->id())
                <span class="font-normal text-muted">{{ __('admin.you') }}</span>
            @endif
        </x-slot:title>
    </x-page-header>

    <form wire:submit="save">
        {{ $this->form }}

        <x-form-actions>
            <x-button.action type="submit">
                {{ __('admin.save_changes') }}
            </x-button.action>

            <x-button.cancel href="{{ route('users.show', $user) }}" />
        </x-form-actions>
    </form>
</div>
