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
</nav>
