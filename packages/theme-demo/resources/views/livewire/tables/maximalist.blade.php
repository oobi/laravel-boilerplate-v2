@section('page-title', __('theme-demo::messages.tables_title'))

<div class="flex flex-col gap-4">
    <x-page-header :title="__('theme-demo::messages.tables_title')" :description="__('theme-demo::messages.tables_description')" />

    <x-tabs-nav>
        @include('theme-demo::livewire.tables._tabs')

        <x-slot:content>
            <x-table-header>
                <x-slot:search>
                    <x-table-search id="maximalist-table-search" model="search" :label="__('Search')" />
                </x-slot:search>

                <x-slot:filters>
                    <x-table-filter-select
                        id="maximalist-status-filter"
                        model="statusFilter"
                        :label="__('Status')"
                        :placeholder="__('All statuses')"
                        :options="$this->statusOptions"
                        class="min-w-36"
                    />
                </x-slot:filters>

                <x-slot:counts>
                    {{ __(':total total', ['total' => $this->rows->total()]) }}
                </x-slot:counts>
            </x-table-header>

            @if (count($selected))
                <div class="ui-toolbar mt-4 flex items-center justify-between">
                    <span class="text-sm font-medium">{{ __(':count selected', ['count' => count($selected)]) }}</span>
                    <div class="flex gap-2">
                        <x-button.cancel size="sm" wire:click="clearSelection">{{ __('Clear') }}</x-button.cancel>
                        <x-button.warning size="sm" wire:click="bulkArchive">{{ __('Archive selected') }}</x-button.warning>
                    </div>
                </div>
            @endif

            <div class="daisy-table mt-4">
                <div class="daisy-table__scroll">
                    <table class="table">
                    <thead>
                        <tr>
                            <th class="w-10">
                                <input type="checkbox" class="checkbox checkbox-sm" wire:click="toggleSelectAll" @checked(count($selected) && count($selected) === $this->rows->total())>
                            </th>
                            @foreach (['name' => __('Name'), 'status' => __('Status'), 'joinedAt' => __('Joined')] as $column => $label)
                                <th class="cursor-pointer select-none" wire:click="sortBy('{{ $column }}')">
                                    {{ $label }}
                                    @if ($sortColumn === $column)
                                        <x-heroicon-o-chevron-up class="inline h-3 w-3 {{ $sortDirection === 'desc' ? 'rotate-180' : '' }}" />
                                    @endif
                                </th>
                            @endforeach
                            <th class="w-10"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->rows as $row)
                            <tr wire:key="maximalist-row-{{ $row->id }}">
                                <td>
                                    <input type="checkbox" class="checkbox checkbox-sm" wire:model.live="selected" value="{{ $row->id }}">
                                </td>
                                <td class="flex items-center gap-3">
                                    <x-avatar :name="$row->name" size="sm" />
                                    <div>
                                        <div class="font-medium">{{ $row->name }}</div>
                                        <div class="text-xs text-muted">{{ $row->email }}</div>
                                    </div>
                                </td>
                                <td><x-badge :value="$row->status" /></td>
                                <td>{{ $row->joinedAt }}</td>
                                <td>
                                    <x-button.icon :aria-label="__('Show details')" :aria-expanded="in_array($row->id, $expanded, true) ? 'true' : 'false'" wire:click="toggleExpand({{ $row->id }})">
                                        <x-heroicon-o-chevron-down class="h-4 w-4 transition-transform {{ in_array($row->id, $expanded, true) ? 'rotate-180' : '' }}" />
                                    </x-button.icon>
                                </td>
                            </tr>
                            @if (in_array($row->id, $expanded, true))
                                <tr wire:key="maximalist-row-{{ $row->id }}-detail">
                                    <td colspan="5" class="bg-base-200/50 text-sm text-base-content/70">{{ $row->detail }}</td>
                                </tr>
                            @endif
                        @empty
                            <tr>
                                <td colspan="5" class="py-8 text-center text-muted">{{ __('No matching rows.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                    </table>
                </div>

                <footer class="daisy-table__pagination" aria-label="{{ __('filament::components/pagination.label') }}">
                    <span class="daisy-table__pagination-overview">{{ $this->paginationOverview() }}</span>

                    <label class="daisy-table__pagination-per-page">
                        <span>{{ __('filament::components/pagination.fields.records_per_page.label') }}</span>
                        <select class="select select-sm" wire:model.live="perPage">
                            @foreach ([10, 25] as $pageSize)
                                <option value="{{ $pageSize }}">{{ $pageSize }}</option>
                            @endforeach
                        </select>
                    </label>

                    <nav class="daisy-table__pagination-actions" aria-label="{{ __('filament::components/pagination.label') }}">
                        <x-button color="neutral" variant="outline" size="sm" wire:click="previousPage" :disabled="$this->rows->onFirstPage()">
                            <x-heroicon-o-chevron-left class="h-4 w-4" />
                            <span class="daisy-table__pagination-action-label">{{ __('filament::components/pagination.actions.previous.label') }}</span>
                        </x-button>

                        <x-button-group :label="__('Pages')" size="sm" class="daisy-table__pagination-pages">
                            @for ($p = 1; $p <= $this->rows->lastPage(); $p++)
                                <x-button-group.item :active="$p === $this->rows->currentPage()" wire:click="gotoPage({{ $p }})">{{ $p }}</x-button-group.item>
                            @endfor
                        </x-button-group>

                        <x-button color="neutral" variant="outline" size="sm" wire:click="nextPage" :disabled="! $this->rows->hasMorePages()">
                            <span class="daisy-table__pagination-action-label">{{ __('filament::components/pagination.actions.next.label') }}</span>
                            <x-heroicon-o-chevron-right class="h-4 w-4" />
                        </x-button>
                    </nav>
                </footer>
            </div>
        </x-slot:content>
    </x-tabs-nav>
</div>
