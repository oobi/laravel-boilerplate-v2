@props(['team'])

{{-- Only teams the user can enter are switchable (active, and not suspended in — see ResolveTeamContext). --}}
@php($teams = auth()->user()->accessibleTeams()->orderBy('name')->get())

<div class="dropdown w-full">
    <div tabindex="0" role="button" class="flex w-full items-center gap-2 rounded-box border border-base-300 bg-base-100 px-3 py-2 text-left transition hover:bg-base-200">
        <div class="min-w-0 flex-1">
            <div class="text-[0.65rem] font-medium uppercase tracking-wide text-base-content/50">{{ config('teams.labels.singular', 'Team') }}</div>
            <div class="truncate text-sm font-semibold text-base-content">{{ $team->name }}</div>
        </div>
        @if ($teams->count() > 1)
            <x-heroicon-o-chevron-up-down class="h-4 w-4 flex-shrink-0 text-base-content/50" />
        @endif
    </div>

    @if ($teams->count() > 1)
        <ul tabindex="0" class="menu dropdown-content z-50 mt-1 w-full min-w-56 rounded-box border border-base-300 bg-base-100 p-2 shadow-lg">
            @foreach ($teams as $t)
                <li>
                    <a href="{{ route('team.dashboard', ['team' => $t->slug]) }}" @class(['font-semibold' => $t->is($team)])>
                        <span class="truncate">{{ $t->name }}</span>
                        @if ($t->is($team))
                            <x-heroicon-o-check class="ml-auto h-4 w-4 text-primary" />
                        @endif
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</div>
