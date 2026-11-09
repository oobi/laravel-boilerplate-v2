{{-- Each changed field, before and after; the part of a short value that changed is marked. --}}
<x-table-scroll>
    <table class="table">
        <thead>
            <tr>
                <th>{{ __('audit.field') }}</th>
                @if ($hasBefore)
                    <th>{{ __('audit.before') }}</th>
                @endif
                @if ($hasAfter)
                    <th>{{ __('audit.after') }}</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @foreach ($changes as $change)
                <tr class="align-top">
                    <td>
                        <div class="font-medium">{{ $change['label'] }}</div>
                        @if (strcasecmp($change['label'], $change['field']) !== 0)
                            <div class="font-mono text-xs text-muted wrap-anywhere">{{ $change['field'] }}</div>
                        @endif
                    </td>
                    @if ($hasBefore)
                        <td @class(['wrap-anywhere text-muted', 'italic' => $change['before']['quiet']])>{{ $change['before']['text'] }}</td>
                    @endif
                    @if ($hasAfter)
                        <td @class(['wrap-anywhere bg-soft-success', 'italic text-muted' => $change['after']['quiet']])>
                            @if ($change['after']['changed'])
                                {{ $change['after']['changed'][0] }}<mark class="ui-diff-added">{{ $change['after']['changed'][1] }}</mark>{{ $change['after']['changed'][2] }}
                            @else
                                {{ $change['after']['text'] }}
                            @endif
                        </td>
                    @endif
                </tr>
            @endforeach
        </tbody>
    </table>
</x-table-scroll>
