<div>
    <x-table-header>
        <x-slot:search>
            <x-table-search id="members-search" :label="team_trans('members.search')" />
        </x-slot:search>

        <x-slot:filters>
            <x-table-filter-select
                id="members-role-filter"
                model="tableFilters.role.value"
                :label="\Concise\Teams\Models\Team::allowsMultipleRoles() ? team_trans('members.roles') : team_trans('members.role')"
                :placeholder="__('admin.all_roles')"
                :options="$this->roleFilterOptions()"
                class="min-w-36"
            />

            <x-table-filter-select
                id="members-status-filter"
                model="tableFilters.status.value"
                :label="__('admin.status')"
                :placeholder="__('admin.all_statuses')"
                :options="\Concise\Teams\Livewire\Team\MembersTable::statusOptions()"
                class="min-w-36"
            />
        </x-slot:filters>

        @if ($this->addMemberAction->isVisible())
            <x-slot:actions>
                {{ $this->addMemberAction }}
            </x-slot:actions>
        @endif
    </x-table-header>

    {{ $this->table }}

    <x-filament-actions::modals />
</div>
