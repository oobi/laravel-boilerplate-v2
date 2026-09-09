@props(['team'])

@php($teams = auth()->user()->teams()->orderBy('name')->get())

<div class="dropdown">
    <div tabindex="0" role="button" class="btn btn-ghost btn-sm gap-2 normal-case">
        <span class="max-w-48 truncate font-semibold">{{ $team->name }}</span>
        @if ($teams->count() > 1)
            <x-heroicon-o-chevron-up-down class="h-4 w-4 opacity-60" />
        @endif
    </div>

    @if ($teams->count() > 1)
        <ul tabindex="0" class="menu dropdown-content z-50 mt-2 w-60 rounded-box border border-base-300 bg-base-100 p-2 shadow-lg">
            <li class="menu-title">{{ config('teams.labels.plural', 'Teams') }}</li>
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
