@php
    /** @var \App\Models\User $record */
    $record = $getRecord();
    $count = (int) $record->teams_count;
    $memberships = route('users.show', $record).'#team-memberships';
    $counted = team_trans_choice('memberships.count', $count, ['count' => $count]);
@endphp

{{-- The Users list's Teams cell (UserListColumns): one team by name, linked when the viewer may open it;
     several as a count linking to the user's Team memberships. In a phone's compact row (below sm) it's
     always the count, after a divider, since a bare team name under a person's name doesn't say what it is;
     with no teams it's visually hidden there, still read out (filament-table.css, [data-cell-empty]). --}}
@if ($count === 0)
    <div class="fi-ta-text text-sm text-muted" data-cell-empty>
        <span aria-hidden="true">–</span>
        <span class="sr-only">{{ $counted }}</span>
    </div>
@else
    <div class="fi-ta-text flex items-center gap-1.5 text-sm">
        <span class="h-4 w-px bg-base-300 sm:hidden" aria-hidden="true"></span>

        @if ($count > 1)
            <a href="{{ $memberships }}" class="link link-hover">{{ $counted }}</a>
        @else
            <a href="{{ $memberships }}" class="link link-hover sm:hidden">{{ $counted }}</a>

            @if ($linked)
                <a href="{{ route('teams.show', $record->only_team_slug) }}" class="link link-hover max-sm:hidden">{{ $record->only_team_name }}</a>
            @else
                <span class="max-sm:hidden">{{ $record->only_team_name }}</span>
            @endif
        @endif
    </div>
@endif
