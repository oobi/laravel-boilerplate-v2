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
            <option value="{{ $option->id }}">
                {{ $option->name }}@if ($option->requires_two_factor) ({{ __('admin.two_factor_required_suffix') }})@endif
            </option>
        @endforeach
    </x-form-select>

    @unless ($this->canEditRole())
        {{-- A head office role with permissions the viewer lacks, or their own: shown, not changed. --}}
        <x-banner variant="info" role="status" class="mb-6">
            {{ __('admin.role_read_only') }}
        </x-banner>
    @endunless

    <form wire:submit="save">
        {{ $form }}

        @if ($this->canEditRole())
            <div class="mt-6 flex items-center gap-4">
                <x-button.action type="submit">
                    {{ __('admin.save_changes') }}
                </x-button.action>
            </div>
        @endif
    </form>
@else
    <x-alert color="info">
        {{ __('admin.no_roles_found') }}
    </x-alert>
@endif
