@section('page-title', __('theme-demo::messages.tables_title'))

<div class="flex flex-col gap-4">
    <x-page-header :title="__('theme-demo::messages.tables_title')" :description="__('theme-demo::messages.tables_wide_description')" />

    <x-tabs-nav>
        @include('theme-demo::livewire.tables._tabs')

        <x-slot:content>
            {{-- ~20 columns and 30 rows, to judge the rail, header and fades on a hand-built table. --}}
            <x-table-scroll>
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ __('Name') }}</th>
                            <th>{{ __('Email') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Joined') }}</th>
                            @foreach ($this->columnKeys() as $key)
                                <th>{{ $this->columnLabel($key) }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->rows() as $index => $row)
                            <tr wire:key="wide-row-{{ $index }}">
                                <td>
                                    <div class="flex items-center gap-3">
                                        <x-avatar :name="$row['name']" size="sm" />
                                        <span class="font-medium">{{ $row['name'] }}</span>
                                    </div>
                                </td>
                                <td>{{ $row['email'] }}</td>
                                <td><x-status :value="$row['status']" /></td>
                                <td>{{ $row['joined_at'] }}</td>
                                @foreach ($this->columnKeys() as $key)
                                    <td>{{ $row[$key] }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-table-scroll>
        </x-slot:content>
    </x-tabs-nav>
</div>
