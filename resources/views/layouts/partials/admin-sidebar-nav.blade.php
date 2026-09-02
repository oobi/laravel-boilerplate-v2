{{-- Shared nav items for both the mobile drawer and the persistent desktop sidebar. --}}
<nav class="flex-1 space-y-1 overflow-y-auto p-4">
    <a href="{{ route('dashboard') }}" class="nav-item {{ request()->routeIs('dashboard') ? 'nav-item-active' : '' }}">
        <x-heroicon-o-squares-2x2 class="h-4 w-4 flex-shrink-0" />
        <span>{{ __('Dashboard') }}</span>
    </a>

    @can('access admin panel')
        <div class="pt-2 pb-1">
            <div class="border-t border-base-300"></div>
        </div>

        <div x-data="{ open: {{ request()->routeIs('users.*') ? 'true' : '$persist(true).as(\'nav-management\')' }} }">
            <button type="button" @click="open = !open" class="nav-section-label flex w-full items-center justify-between">
                <span>{{ __('Management') }}</span>
                <x-heroicon-o-chevron-down class="h-4 w-4 transition-transform" ::class="{ 'rotate-180': !open }" />
            </button>
            <div x-show="open" x-collapse>
                <a href="{{ route('users.index') }}" class="nav-item {{ request()->routeIs('users.*') ? 'nav-item-active' : '' }}">
                    <x-heroicon-o-user class="h-4 w-4 flex-shrink-0" />
                    <span>{{ __('Users') }}</span>
                </a>
            </div>
        </div>
    @endcan

    {{-- Add-on nav sections (e.g. the style-demo dev package) register here via NavRegistry::extend() --}}
    @foreach (\App\Support\Navigation\NavRegistry::groups(auth()->user()) as $group)
        @php $groupRoutes = collect($group->items())->flatMap(fn ($item) => $item->activeRoutes())->all(); @endphp
        <div class="pt-2 pb-1">
            <div class="border-t border-base-300"></div>
        </div>
        <div x-data="{ open: {{ request()->routeIs($groupRoutes) ? 'true' : '$persist(true).as(\'nav-'.\Illuminate\Support\Str::slug($group->label()).'\')' }} }">
            <button type="button" @click="open = !open" class="nav-section-label flex w-full items-center justify-between">
                <span class="flex items-center gap-2">
                    @if ($group->icon())
                        <x-dynamic-component :component="$group->icon()" class="h-4 w-4 flex-shrink-0" />
                    @endif
                    {{ $group->label() }}
                </span>
                <x-heroicon-o-chevron-down class="h-4 w-4 transition-transform" ::class="{ 'rotate-180': !open }" />
            </button>
            <div x-show="open" x-collapse>
                @foreach ($group->items() as $item)
                    <a href="{{ route($item->route) }}" class="nav-item {{ request()->routeIs($item->activeRoutes()) ? 'nav-item-active' : '' }}">
                        <x-dynamic-component :component="$item->icon" class="h-4 w-4 flex-shrink-0" />
                        <span>{{ $item->label }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    @endforeach
</nav>
