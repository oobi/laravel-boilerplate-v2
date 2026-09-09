{{-- Team-area sidebar links. Dashboard is the team home, presented prominently
     and distinct from the itemized sections below. Additional sections (Members,
     Settings, …) are curated via Concise\Teams\Support\Navigation\TeamNavRegistry
     the same way add-ons extend the system nav — abilities resolve against the
     current team, and routes carry its slug. --}}
@php($viewer = auth()->user())
<nav class="flex-1 space-y-1 overflow-y-auto p-4">
    {{-- Team select — sits at the top of the left nav, above the links --}}
    <div class="mb-3">
        <x-teams::team-switcher :team="$team" />
    </div>

    <a
        href="{{ route('team.dashboard', ['team' => $team->slug]) }}"
        @class([
            'flex items-center gap-3 rounded-box px-3 py-2 text-sm font-semibold transition',
            'bg-primary text-primary-content' => request()->routeIs('team.dashboard'),
            'text-base-content hover:bg-base-200' => ! request()->routeIs('team.dashboard'),
        ])
    >
        <x-heroicon-o-home class="h-5 w-5" />
        {{ __('Dashboard') }}
    </a>

    @foreach (\Concise\Teams\Support\Navigation\TeamNavRegistry::resolve($viewer, $team) as $node)
        @if ($node instanceof \App\Support\Navigation\Registry\NavGroup)
            <x-nav-group :label="$node->label" :icon="$node->icon" :routes="$node->activeRoutes()">
                @foreach (\Concise\Teams\Support\Navigation\TeamNavRegistry::visibleItems($node, $viewer, $team) as $item)
                    <x-nav-item :href="route($item->route, ['team' => $team->slug])" :icon="$item->icon" :routes="$item->activeRoutes()">
                        {{ $item->label }}
                    </x-nav-item>
                @endforeach
            </x-nav-group>
        @else
            <x-nav-item :href="route($node->route, ['team' => $team->slug])" :icon="$node->icon" :routes="$node->activeRoutes()">
                {{ $node->label }}
            </x-nav-item>
        @endif
    @endforeach
</nav>
