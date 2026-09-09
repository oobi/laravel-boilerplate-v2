{{-- Team-area sidebar. Curated via Concise\Teams\Support\Navigation\TeamNavRegistry;
     add-ons contribute team nav the same way they contribute system nav — but
     abilities resolve against the current team, and routes carry its slug. --}}
@php($viewer = auth()->user())
<nav class="flex-1 space-y-1 overflow-y-auto p-4">
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
