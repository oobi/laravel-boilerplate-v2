<div>
    <x-table-header>
        <x-slot:search>
            <x-table-search id="domains-search" :label="team_trans('domains.search')" />
        </x-slot:search>

        <x-slot:filters>
            <x-table-filter-select
                id="domains-status-filter"
                model="tableFilters.status.value"
                :label="team_trans('domains.status')"
                :placeholder="__('admin.all_statuses')"
                :options="\Concise\Teams\Livewire\Team\ManageDomains::statusOptions()"
                class="min-w-36"
            />
        </x-slot:filters>

        @if ($this->canManage())
            <x-slot:actions>
                {{ $this->addDomainAction }}
            </x-slot:actions>
        @endif
    </x-table-header>

    {{ $this->table }}

    <x-filament-actions::modals />
</div>
