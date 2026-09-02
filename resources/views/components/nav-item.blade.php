@props(['href', 'icon', 'routes' => []])
@php $active = $routes !== [] && request()->routeIs($routes); @endphp
<a href="{{ $href }}" class="nav-item {{ $active ? 'nav-item-active' : '' }}">
    <x-dynamic-component :component="$icon" class="h-4 w-4 flex-shrink-0" />
    <span>{{ $slot }}</span>
</a>
