@php
    /** @var \App\Models\User $record */
    $record = $getRecord();
    $isCurrent = $record->id === auth()->id();
@endphp

<div class="fi-ta-text flex items-center gap-3 py-3">
    <x-avatar :user="$record" color="info" class="shrink-0" />

    {{-- Name, email, "You" badge --}}
    <div>
        <div class="text-sm font-medium">
            <a href="{{ route('users.show', $record) }}" class="link link-hover">
                {{ $record->list_name }}
            </a>
        </div>
        <div class="text-sm text-base-content/60">
            {{ $record->email }}
        </div>
        @if ($isCurrent)
            <x-badge color="success" class="mt-1">{{ __('admin.you') }}</x-badge>
        @endif
    </div>
</div>
