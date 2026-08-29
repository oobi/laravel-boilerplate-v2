@section('page-title', __('theme-demo::messages.tables_title'))

<div class="flex flex-col gap-4">
    <x-page-header :title="__('theme-demo::messages.tables_title')" :description="__('theme-demo::messages.tables_description')" />

    <x-tabs.nav>
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
                        <button type="button" class="btn btn-sm" wire:click="clearSelection">{{ __('Clear') }}</button>
                        <button type="button" class="btn btn-sm btn-warning" wire:click="bulkArchive">{{ __('Archive selected') }}</button>
                    </div>
                </div>
            @endif

            <div class="mt-4 overflow-x-auto">
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
                                        <div class="text-xs text-base-content/60">{{ $row->email }}</div>
                                    </div>
                                </td>
                                <td><x-badge :value="$row->status" /></td>
                                <td>{{ $row->joinedAt }}</td>
                                <td>
                                    <button type="button" class="btn btn-ghost btn-square btn-sm" wire:click="toggleExpand({{ $row->id }})">
                                        <x-heroicon-o-chevron-down class="h-4 w-4 transition-transform {{ in_array($row->id, $expanded, true) ? 'rotate-180' : '' }}" />
                                    </button>
                                </td>
                            </tr>
                            @if (in_array($row->id, $expanded, true))
                                <tr wire:key="maximalist-row-{{ $row->id }}-detail">
                                    <td colspan="5" class="bg-base-200/50 text-sm text-base-content/70">{{ $row->detail }}</td>
                                </tr>
                            @endif
                        @empty
                            <tr>
                                <td colspan="5" class="py-8 text-center text-base-content/60">{{ __('No matching rows.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($this->rows->lastPage() > 1)
                <div class="join mt-4 flex justify-center">
                    <button type="button" class="join-item btn btn-sm" wire:click="previousPage" @disabled($this->rows->onFirstPage())>«</button>
                    @for ($p = 1; $p <= $this->rows->lastPage(); $p++)
                        <button type="button" class="join-item btn btn-sm {{ $p === $this->rows->currentPage() ? 'btn-active' : '' }}" wire:click="gotoPage({{ $p }})">{{ $p }}</button>
                    @endfor
                    <button type="button" class="join-item btn btn-sm" wire:click="nextPage" @disabled(! $this->rows->hasMorePages())>»</button>
                </div>
            @endif
        </x-slot:content>
    </x-tabs.nav>
</div>
