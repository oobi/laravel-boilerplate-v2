{{-- Block-form php directive only: mixing it with the inline parenthesised form in one file makes Blade's block regex swallow everything between them. --}}
@php $plural = \Concise\Teams\Support\TeamLabels::plural(); @endphp
<div>
    @section('page-title', $plural)

    <x-page-header :title="$plural" :description="team_trans('admin.description')">
        <x-slot:actions>
            {{ $this->createTeamAction }}
        </x-slot:actions>
    </x-page-header>

    @php $trashedFilterValue = data_get($tableFilters, 'trashed.value') ?? ''; @endphp

    <x-table-header>
        <x-slot:search>
            <x-table-search id="teams-search" :label="team_trans('admin.search')" />
        </x-slot:search>

        <x-slot:filters>
            <x-table-filter-select
                id="teams-status-filter"
                model="tableFilters.active.value"
                :label="__('admin.status')"
                :placeholder="__('admin.all_statuses')"
                :options="\Concise\Teams\Livewire\Admin\Teams\ListTeams::statusOptions()"
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
