<div>
    {{-- Custom domains are an incomplete placeholder (no cross-domain session handoff
         yet), so say so unambiguously wherever the table is shown — team area and admin. --}}
    <x-banner variant="warning" class="mb-4">
        <p class="font-semibold">{{ team_trans('domains.placeholder_title') }}</p>
        <p class="mt-1 text-sm">{{ team_trans('domains.placeholder_body') }}</p>
    </x-banner>

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
