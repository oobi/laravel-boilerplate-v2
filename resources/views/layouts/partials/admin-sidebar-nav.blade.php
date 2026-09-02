{{-- Shared nav items for both the mobile drawer and the persistent desktop sidebar.
     Menu is curated in App\Support\Navigation\AdminNav; add-ons contribute via
     NavRegistry::item()/group() from their own service provider's boot(). --}}
<nav class="flex-1 space-y-1 overflow-y-auto p-4">
    @foreach (\App\Support\Navigation\Registry\NavRegistry::resolve(auth()->user()) as $node)
        @if ($node instanceof \App\Support\Navigation\Registry\NavGroup)
            <x-nav.group :label="$node->label" :icon="$node->icon" :routes="$node->activeRoutes()">
                @foreach ($node->visibleItems(auth()->user()) as $item)
                    <x-nav.item :href="route($item->route)" :icon="$item->icon" :routes="$item->activeRoutes()">
                        {{ $item->label }}
                    </x-nav.item>
                @endforeach
            </x-nav.group>
        @else
            <x-nav.item :href="route($node->route)" :icon="$node->icon" :routes="$node->activeRoutes()">
                {{ $node->label }}
            </x-nav.item>
        @endif
    @endforeach
</nav>
