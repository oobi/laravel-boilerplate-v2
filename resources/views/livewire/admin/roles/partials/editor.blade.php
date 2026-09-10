{{-- The role picker + edit form for the open scope; the same markup with or without the scope tabs around it. --}}
@if ($role)
    <x-form-select
        name="selectedRoleId"
        :label="__('admin.select_role')"
        wire:model.live="selectedRoleId"
        :floating="false"
        class="mb-6 max-w-sm"
    >
        @foreach ($roles as $option)
            <option value="{{ $option->id }}">{{ $option->name }}</option>
        @endforeach
    </x-form-select>

    <form wire:submit="save">
        {{ $form }}

        <div class="mt-6 flex items-center gap-4">
            <x-button.action type="submit">
                {{ __('admin.save_changes') }}
            </x-button.action>
        </div>
    </form>
@else
    <x-alert color="info">
        {{ __('admin.no_roles_found') }}
    </x-alert>
@endif
