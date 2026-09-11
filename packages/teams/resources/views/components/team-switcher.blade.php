@props(['team'])

@php
    // Only teams the user can enter are switchable (active, and not suspended in — see ResolveTeamContext).
    $teams = auth()->user()->accessibleTeams()->orderBy('name')->get();
    $canCreate = \Illuminate\Support\Facades\Gate::allows(
        \Concise\Teams\Enums\TeamAbility::CREATE,
        \Concise\Teams\Models\Team::class,
    );
    // A lone team with nowhere else to go needs no menu.
    $showMenu = $teams->count() > 1 || $canCreate;
@endphp

<div class="dropdown w-full">
    <div tabindex="0" role="button" class="flex w-full items-center gap-2 rounded-box border border-base-300 bg-base-100 px-3 py-2 text-left transition hover:bg-base-200">
        <div class="min-w-0 flex-1">
            <div class="text-[0.65rem] font-medium uppercase tracking-wide text-base-content/50">{{ \Concise\Teams\Support\TeamLabels::singular() }}</div>
            {{-- The sidebar is narrow by nature, so the trigger truncates and carries the full name as a tooltip. --}}
            <div class="truncate text-sm font-semibold text-base-content" title="{{ $team->name }}">{{ $team->name }}</div>
        </div>
        @if ($showMenu)
            <x-heroicon-o-chevron-up-down class="h-4 w-4 shrink-0 text-base-content/50" />
        @endif
    </div>

    @if ($showMenu)
        {{-- Wider than the sidebar on purpose: a dropdown panel needn't match its trigger, and long
             team names are unreadable squeezed into the rail. --}}
        <ul tabindex="0" class="menu dropdown-content z-50 mt-1 w-72 max-w-[calc(100vw-3rem)] rounded-box border border-base-300 bg-base-100 p-2 shadow-lg">
            @foreach ($teams as $t)
                <li>
                    <a href="{{ route('team.dashboard', ['team' => $t->slug]) }}" @class(['font-semibold' => $t->is($team)]) title="{{ $t->name }}">
                        <span class="truncate">{{ $t->name }}</span>
                        @if ($t->is($team))
                            <x-heroicon-o-check class="ml-auto h-4 w-4 shrink-0 text-primary" />
                        @endif
                    </a>
                </li>
            @endforeach

            <li></li>

            @if ($teams->count() > 1)
                <li>
                    <a href="{{ route('team.select') }}" class="text-base-content/70">
                        <x-heroicon-o-squares-2x2 class="h-4 w-4" />
                        {{ team_trans('select.all') }}
                    </a>
                </li>
            @endif

            @if ($canCreate)
                <li>
                    <a href="{{ route('team.select', ['create' => 1]) }}" class="text-base-content/70">
                        <x-heroicon-o-plus class="h-4 w-4" />
                        {{ team_trans('create.action') }}
                    </a>
                </li>
            @endif
        </ul>
    @endif
</div>
