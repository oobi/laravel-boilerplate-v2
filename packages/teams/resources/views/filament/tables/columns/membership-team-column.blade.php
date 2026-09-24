@php
    /** @var \Concise\Teams\Models\Team $record */
    $record = $getRecord();
@endphp

{{-- A team on a user's memberships list: its name (linked when the viewer may open it), flagged when inactive. --}}
<div class="fi-ta-text flex items-center gap-2 py-3 text-sm font-medium">
    @if ($linked)
        <a href="{{ route('teams.show', $record) }}" class="link link-hover">
            {{ $record->name }}
        </a>
    @else
        {{ $record->name }}
    @endif

    @unless ($record->active)
        <x-badge color="neutral">{{ team_trans('admin.inactive') }}</x-badge>
    @endunless
</div>
