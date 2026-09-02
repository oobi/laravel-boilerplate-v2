@props(['label', 'icon' => null, 'routes' => []])
@php
    $active = $routes !== [] && request()->routeIs($routes);
    $persistKey = 'nav-'.\Illuminate\Support\Str::slug($label);
    $openExpression = $active ? 'true' : "\$persist(true).as('{$persistKey}')";
@endphp
<div class="pt-2 pb-1">
    <div class="border-t border-base-300"></div>
</div>
<div x-data="{ open: {{ $openExpression }} }">
    <button type="button" @click="open = !open" class="nav-section-label flex w-full items-center justify-between">
        <span class="flex items-center gap-2">
            @if ($icon)
                <x-dynamic-component :component="$icon" class="h-4 w-4 flex-shrink-0" />
            @endif
            {{ $label }}
        </span>
        <x-heroicon-o-chevron-down class="h-4 w-4 transition-transform" ::class="{ 'rotate-180': !open }" />
    </button>
    <div x-show="open" x-collapse>
        {{ $slot }}
    </div>
</div>
