<div>
    @section('page-title', __('admin.list'))

    <x-page-header :title="__('admin.users')" :description="__('admin.users_description')">
        <x-slot:actions>
            <x-button href="{{ route('users.create') }}">
                {{ __('admin.add_user') }}
            </x-button>
        </x-slot:actions>
    </x-page-header>

    @php $trashedFilterValue = data_get($tableFilters, 'trashed.value') ?? ''; @endphp

    <x-table-header>
        <x-slot:search>
            <x-table-search id="users-search" :label="__('admin.search_placeholder')" />
        </x-slot:search>

        <x-slot:filters>
            <x-table-filter-select
                id="users-status-filter"
                model="tableFilters.status.value"
                :label="__('admin.status')"
                :placeholder="__('admin.all_statuses')"
                :options="\App\Enums\UserStatus::options()"
                class="min-w-36"
            />

            <x-table-filter-select
                id="users-role-filter"
                model="tableFilters.role.value"
                :label="__('admin.roles')"
                :placeholder="__('admin.all_roles')"
                :options="$this->roleFilterOptions()"
                class="min-w-36"
            />
        </x-slot:filters>

        <x-slot:counts>
            <x-table-trash-toggle
                :value="$trashedFilterValue"
                :active-count="$this->activeRecordsCount()"
                :trashed-count="$this->trashedRecordsCount()"
            />
        </x-slot:counts>

        @if ($trashedFilterValue === '0' && $this->trashedRecordsCount() > 0)
            <x-slot:actions>
                <x-table-empty-trash-action>
                    {{ __('admin.empty_trash') }}
                </x-table-empty-trash-action>
            </x-slot:actions>
        @endif
    </x-table-header>

    {{ $this->table }}

    <x-filament-actions::modals />
</div>
