@php
    /**
     * The listed user: the row itself, or a related User passed as the column's
     * state (e.g. ViewColumn::make('owner') on a teams table), see .ai/rules/tables.md.
     * A related user is secondary to the row, so it's compact: a small avatar and
     * the linked name, no email, the "You" badge beside the name.
     *
     * @var \App\Models\User $record
     */
    $state = $getState();
    $isRelated = $state instanceof \App\Models\User;
    $record = $isRelated ? $state : $getRecord();
    $isCurrent = $record->id === auth()->id();
@endphp

@if ($isRelated)
    <div class="fi-ta-text flex items-center gap-2">
        <x-avatar :user="$record" color="info" size="sm" class="shrink-0" />

        <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm sm:min-w-0 sm:whitespace-normal">
            <a href="{{ route('users.show', $record) }}" class="link link-hover">
                {{ $record->list_name }}
            </a>
            @if ($isCurrent)
                <x-badge color="success">{{ __('admin.you') }}</x-badge>
            @endif
        </div>
    </div>
@else
    <div class="fi-ta-text flex items-center gap-3 py-3">
        <x-avatar :user="$record" color="info" class="shrink-0" />

        {{-- Name, email, "You" badge. From `sm` they wrap (Filament's rows don't there) rather than widen a squeezed table. --}}
        <div class="sm:min-w-0 sm:whitespace-normal">
            <div class="text-sm font-medium">
                <a href="{{ route('users.show', $record) }}" class="link link-hover">
                    {{ $record->list_name }}
                </a>
            </div>
            <div class="text-sm text-muted sm:wrap-anywhere">
                {{ $record->email }}
            </div>
            @if ($isCurrent)
                <x-badge color="success" class="mt-1">{{ __('admin.you') }}</x-badge>
            @endif
        </div>
    </div>
@endif
