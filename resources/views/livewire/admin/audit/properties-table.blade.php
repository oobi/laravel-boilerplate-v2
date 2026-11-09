{{-- Whatever else the entry recorded; a list or a set of values reads as indented JSON. --}}
<x-table-scroll>
    <table class="table">
        <thead>
            <tr>
                <th>{{ __('audit.name') }}</th>
                <th>{{ __('audit.value') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($properties as $property)
                <tr class="align-top">
                    <td>
                        <div class="font-medium">{{ $property['label'] }}</div>
                        @if (strcasecmp($property['label'], $property['name']) !== 0)
                            <div class="font-mono text-xs text-muted wrap-anywhere">{{ $property['name'] }}</div>
                        @endif
                    </td>
                    <td class="wrap-anywhere">
                        @if ($property['structured'])
                            <pre class="font-mono text-sm whitespace-pre-wrap wrap-anywhere">{{ $property['value'] }}</pre>
                        @else
                            {{ $property['value'] }}
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</x-table-scroll>
