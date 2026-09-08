<div>
    @section('page-title', __('admin.roles'))

    <x-page-header :title="__('admin.roles')" :description="__('admin.roles_description')">
        <x-slot:actions>
            <x-button href="{{ route('roles.create') }}">
                {{ __('admin.add_role') }}
            </x-button>

            {{ $this->deleteRoleAction }}
        </x-slot:actions>
    </x-page-header>

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
            {{ $this->form }}

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

    <x-filament-actions::modals />
</div>
