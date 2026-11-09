{{-- Who did an audited act, beside their initials: a person links to their page for someone who may open it. --}}
<x-dynamic-component :component="$getEntryWrapperView()" :entry="$entry">
    <div class="flex items-start gap-3">
        @if ($performer['initials'])
            <x-avatar :name="$performer['name']" size="sm" />
        @else
            <x-avatar icon="heroicon-o-cpu-chip" :name="$performer['name']" size="sm" />
        @endif

        <div class="min-w-0 text-sm">
            @if ($performer['url'])
                <a href="{{ $performer['url'] }}" class="link link-hover wrap-anywhere">{{ $performer['name'] }}</a>
            @else
                <div class="wrap-anywhere">{{ $performer['name'] }}</div>
            @endif
            <div class="text-muted">{{ $performer['type'] }}</div>
        </div>
    </div>
</x-dynamic-component>
