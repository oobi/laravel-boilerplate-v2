<div>
    @section('page-title', \Illuminate\Support\Str::limit($entry->description, 60))

    <x-page-header :title="\Illuminate\Support\Str::limit($entry->description, 120)">
        <x-slot:actions>
            <x-button.back href="{{ route('audit.index') }}">
                {{ __('audit.back_to_audit_log') }}
            </x-button.back>
        </x-slot:actions>
    </x-page-header>

    <div class="flex flex-col gap-6">
        {{ $this->entryInfolist }}

        @if ($this->changes()->isNotEmpty())
            <x-card :title="__('audit.changes')" type="panel">
                <x-table-scroll>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>{{ __('audit.field') }}</th>
                                <th>{{ __('audit.before') }}</th>
                                <th>{{ __('audit.after') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->changes() as $change)
                                <tr>
                                    <td class="font-medium">{{ $change['field'] }}</td>
                                    <td class="wrap-anywhere">{{ $change['before'] }}</td>
                                    <td class="wrap-anywhere">{{ $change['after'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-table-scroll>
            </x-card>
        @endif

        @if ($this->properties()->isNotEmpty())
            <x-card :title="__('audit.more')" type="panel">
                <x-table-scroll>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>{{ __('audit.name') }}</th>
                                <th>{{ __('audit.value') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->properties() as $property)
                                <tr>
                                    <td class="font-medium">{{ $property['name'] }}</td>
                                    <td class="wrap-anywhere">{{ $property['value'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-table-scroll>
            </x-card>
        @endif
    </div>
</div>
