@section('page-title', __('theme-demo::messages.filament_table_title'))

<div class="flex flex-col gap-4">
    <x-page-header :title="__('theme-demo::messages.filament_table_title')" :description="__('theme-demo::messages.filament_table_description')" />

    <x-tabs.nav>
        @include('theme-demo::livewire.tables._filament-tabs', ['variant' => $variant])

        <x-slot:content>
            @if ($variant === 'custom-header')
                {{-- Same pattern every real admin table uses (see ListUsers) — Filament's own header/search/filter chrome stays hidden (app-wide default, see theme/components/ui/filament-table.css) and this replaces it. --}}
                <x-table-header class="mb-4">
                    <x-slot:search>
                        <x-table-search id="filament-custom-header-search" :label="__('Search')" />
                    </x-slot:search>

                    <x-slot:filters>
                        <x-table-filter-select
                            id="filament-custom-header-status-filter"
                            model="tableFilters.status.value"
                            :label="__('Status')"
                            :placeholder="__('All statuses')"
                            :options="$this->statusOptions()"
                            class="min-w-36"
                        />
                    </x-slot:filters>

                    <x-slot:counts>
                        <x-table-trash-toggle
                            model="trashedFilter"
                            :value="$trashedFilter"
                            :active-count="$this->activeRecordsCount()"
                            :trashed-count="$this->trashedRecordsCount()"
                        />
                    </x-slot:counts>

                    @if ($trashedFilter === '0')
                        <x-slot:actions>
                            <x-table-empty-trash-action>
                                {{ __('Empty trash') }}
                            </x-table-empty-trash-action>
                        </x-slot:actions>
                    @endif
                </x-table-header>

                {{ $this->table }}
            @else
                {{-- Demo-only override, kept OUT of the core theme (theme/components/ui/filament-table.css hides this app-wide since every real admin table supplies its own <x-table-header> instead — see ListUsers). --}}
                <style>
                    .style-demo-native-table-header .fi-ta-header-ctn {
                        display: block;
                    }
                </style>

                <div class="style-demo-native-table-header">
                    {{ $this->table }}
                </div>
            @endif
        </x-slot:content>
    </x-tabs.nav>

    <x-filament-actions::modals />
</div>
