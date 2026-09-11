@php
    /** @var \Concise\Teams\Models\Team $record */
    $record = $getRecord();
@endphp

{{-- Linked team name over its slug — the team counterpart of filament.tables.columns.user-column. --}}
<div class="fi-ta-text py-3">
    <div class="text-sm font-medium">
        <a href="{{ route('teams.show', $record) }}" class="link link-hover">
            {{ $record->name }}
        </a>
    </div>
    <div class="text-sm text-base-content/60">
        {{ $record->slug }}
    </div>
</div>
