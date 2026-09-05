<div>
    @section('page-title', __('admin.roles'))

    <x-page-header :title="__('admin.roles')" :description="__('admin.roles_description')">
        <x-slot:actions>
            <x-button href="{{ route('roles.create') }}">
                {{ __('admin.add_role') }}
            </x-button>
        </x-slot:actions>
    </x-page-header>

    {{ $this->table }}

    <x-filament-actions::modals />
</div>
