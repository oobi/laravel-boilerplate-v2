<div>
    @section('page-title', __('admin.users'))

    <div class="ui-page-header">
        <div>
            <h1 class="ui-page-title">{{ __('admin.users') }}</h1>
            <p class="ui-subtle">{{ __('admin.users_description') }}</p>
        </div>

        <div class="ui-page-actions">
            <a href="{{ route('users.create') }}" class="btn btn-primary">
                {{ __('admin.add_user') }}
            </a>
        </div>
    </div>

    @php $trashedFilterValue = data_get($tableFilters, 'trashed.value') ?? ''; @endphp

    <x-table-header>
        <x-slot:search>
            <x-table-search id="users-search" :label="__('admin.search_placeholder')" />
        </x-slot:search>

        <x-slot:filters>
            <x-table-filter-select
                id="users-system-role-filter"
                model="tableFilters.system_role.value"
                :label="__('admin.system_role')"
                :placeholder="__('admin.all_roles')"
                :options="\App\Enums\SystemRole::options()"
                class="min-w-44"
            />

            <x-table-filter-select
                id="users-status-filter"
                model="tableFilters.status.value"
                :label="__('admin.status')"
                :placeholder="__('admin.all_statuses')"
                :options="\App\Enums\UserStatus::options()"
                class="min-w-36"
            />
        </x-slot:filters>

        <x-slot:counts>
            <x-table-trash-toggle
                :value="$trashedFilterValue"
                :active-count="$this->activeUsersCount()"
                :trashed-count="$this->trashedUsersCount()"
            />
        </x-slot:counts>

        @if ($trashedFilterValue === '0' && $this->trashedUsersCount() > 0)
            <x-slot:actions>
                <x-table-empty-trash-action :confirm="__('admin.empty_trash_confirm')">
                    {{ __('admin.empty_trash') }}
                </x-table-empty-trash-action>
            </x-slot:actions>
        @endif
    </x-table-header>

    {{ $this->table }}

    <x-filament-actions::modals />
</div>
