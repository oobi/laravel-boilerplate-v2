@section('page-title', __('theme-demo::messages.tables_title'))

<div class="flex flex-col gap-4">
    <x-page-header :title="__('theme-demo::messages.tables_title')" :description="__('theme-demo::messages.tables_description')" />

    <x-tabs-nav>
        @include('theme-demo::livewire.tables._tabs')

        <x-slot:content>
            <x-table-header>
                <x-slot:search>
                    <x-table-search id="simple-table-search" model="search" :label="__('Search')" />
                </x-slot:search>
            </x-table-header>

            <x-table-scroll class="mt-4">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ __('Name') }}</th>
                            <th>{{ __('Email') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Joined') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->rows as $row)
                            <tr wire:key="simple-row-{{ $row->id }}">
                                <td>
                                    <div class="flex items-center gap-3">
                                        <x-avatar :name="$row->name" size="sm" />
                                        <span class="font-medium">{{ $row->name }}</span>
                                    </div>
                                </td>
                                <td>{{ $row->email }}</td>
                                <td><x-status :value="$row->status" /></td>
                                <td>{{ $row->joinedAt }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-8 text-center text-muted">{{ __('No matching rows.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </x-table-scroll>
        </x-slot:content>
    </x-tabs-nav>
</div>
