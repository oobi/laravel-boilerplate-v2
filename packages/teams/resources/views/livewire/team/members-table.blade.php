<div>
    <x-table-header>
        <x-slot:search>
            <x-table-search id="members-search" :label="__('Search members…')" />
        </x-slot:search>

        <x-slot:filters>
            <x-table-filter-select
                id="members-role-filter"
                model="tableFilters.role.value"
                :label="\Concise\Teams\Models\Team::allowsMultipleRoles() ? __('Roles') : __('Role')"
                :placeholder="__('All roles')"
                :options="$this->roleOptions()"
                class="min-w-36"
            />

            <x-table-filter-select
                id="members-standing-filter"
                model="tableFilters.standing.value"
                :label="__('Standing')"
                :placeholder="__('Everyone')"
                :options="\Concise\Teams\Livewire\Team\MembersTable::standingOptions()"
                class="min-w-36"
            />
        </x-slot:filters>
    </x-table-header>

    {{ $this->table }}

    <x-filament-actions::modals />
</div>
