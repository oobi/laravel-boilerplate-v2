{{-- Team-area sidebar links. Dashboard is the team home, presented prominently
     and distinct from the itemized sections below. Additional sections (Members,
     Settings, …) are curated via Concise\Teams\Support\Navigation\TeamNavRegistry
     the same way add-ons extend the system nav: abilities resolve against the
     current team, and routes carry its slug. --}}
@php($viewer = auth()->user())
{{-- The switcher sits above these, in the shell's navigationTop slot (layouts/team.blade.php). --}}
<nav class="flex-1 space-y-1 overflow-y-auto p-4 pt-3">

    <x-nav-item
        :href="team_route('team.dashboard', $team)"
        icon="heroicon-o-squares-2x2"
        :routes="['team.dashboard']"
    >
        {{ team_trans('nav.dashboard') }}
    </x-nav-item>

    @foreach (\Concise\Teams\Support\Navigation\TeamNavRegistry::resolve($viewer, $team) as $node)
        @if ($node instanceof \App\Support\Navigation\Registry\NavGroup)
            <x-nav-group :label="$node->label" :icon="$node->icon" :routes="$node->activeRoutes()">
                @foreach (\Concise\Teams\Support\Navigation\TeamNavRegistry::visibleItems($node, $viewer, $team) as $item)
                    <x-nav-item :href="team_route($item->route, $team)" :icon="$item->icon" :routes="$item->activeRoutes()">
                        {{ $item->label }}
                    </x-nav-item>
                @endforeach
            </x-nav-group>
        @else
            <x-nav-item :href="team_route($node->route, $team)" :icon="$node->icon" :routes="$node->activeRoutes()">
                {{ $node->label }}
            </x-nav-item>
        @endif
    @endforeach
</nav>
