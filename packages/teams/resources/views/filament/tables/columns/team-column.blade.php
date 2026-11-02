@php
    /** @var \Concise\Teams\Models\Team $record */
    $record = $getRecord();
@endphp

{{-- Linked team name over its slug: the team counterpart of filament.tables.columns.user-column,
     with a square accent initials tile (a person's is a round info one), so the team leads
     its row and never reads as a user.
     From `sm` it wraps (Filament's rows don't there) rather than widen a squeezed table. --}}
<div class="fi-ta-text flex items-center gap-3 py-3">
    <x-avatar :name="$record->name" color="accent" square class="shrink-0" />

    <div class="sm:min-w-0 sm:whitespace-normal">
        <div class="text-sm font-medium">
            <a href="{{ route('teams.show', $record) }}" class="link link-hover">
                {{ $record->name }}
            </a>
        </div>
        <div class="text-sm text-muted">
            {{ $record->slug }}
        </div>
    </div>
</div>
