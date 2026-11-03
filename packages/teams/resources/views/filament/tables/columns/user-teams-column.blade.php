@php
    /** @var \App\Models\User $record */
    $record = $getRecord();
    $count = (int) $record->teams_count;
@endphp

{{-- The Users list's Teams cell (UserListColumns): one team by name, linked when the viewer may open it;
     several as a count linking to the user's Team memberships. The icon names the cell in a phone's compact row. --}}
@if ($count === 0)
    {{-- A phone's compact row leaves it out rather than show a stray dash under the name. --}}
    <div class="fi-ta-text text-sm text-muted max-sm:hidden" aria-hidden="true">–</div>
    <span class="sr-only">{{ team_trans_choice('memberships.count', 0, ['count' => 0]) }}</span>
@else
    <div class="fi-ta-text flex items-center gap-1.5 text-sm">
        <x-heroicon-o-user-group class="h-4 w-4 shrink-0 text-muted" aria-hidden="true" />

        @if ($count > 1)
            <a href="{{ route('users.show', $record) }}#team-memberships" class="link link-hover">
                {{ team_trans_choice('memberships.count', $count, ['count' => $count]) }}
            </a>
        @elseif ($linked)
            <a href="{{ route('teams.show', $record->only_team_slug) }}" class="link link-hover">{{ $record->only_team_name }}</a>
        @else
            {{ $record->only_team_name }}
        @endif
    </div>
@endif
