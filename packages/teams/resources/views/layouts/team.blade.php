<!DOCTYPE html>
@php
    $themeMode = request()->cookie('theme', 'auto');
    $team = current_team();
@endphp
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="{{ $themeMode === 'dark' ? 'boilerplate-dark' : 'boilerplate' }}" data-theme-mode="{{ $themeMode }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? ($team?->name ? $team->name.' · '.config('app.name') : config('app.name', 'Laravel')) }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <x-theme-init-script />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="font-sans antialiased {{ auth()->check() && auth()->user()->isImpersonated() ? 'is-impersonating' : '' }}">
    <x-impersonation-banner />

    <div class="ui-app">
        <div
            x-data="{ drawerOpen: false }"
            @resize.window="if (window.innerWidth >= 768) drawerOpen = false"
            class="ui-app-inner flex"
        >
            {{-- Mobile drawer backdrop --}}
            <div
                x-show="drawerOpen"
                x-transition.opacity
                @click="drawerOpen = false"
                class="fixed inset-0 z-40 bg-black/50 md:hidden"
                style="display: none;"
            ></div>

            {{-- Sidebar: persistent on desktop, drawer on mobile --}}
            <aside
                class="fixed inset-y-0 left-0 z-50 w-64 -translate-x-full transition-transform md:static md:translate-x-0"
                :class="drawerOpen && '!translate-x-0'"
            >
                <div class="flex h-full w-64 flex-col border-r border-base-300 bg-base-100">
                    <div class="flex h-16 flex-shrink-0 items-center justify-between border-b border-base-300 px-4">
                        <a href="{{ $team ? route('team.dashboard', ['team' => $team->slug]) : url('/') }}" class="min-w-0 flex-1">
                            <x-application-logo />
                        </a>
                        <x-button.icon @click="drawerOpen = false" class="md:hidden" aria-label="{{ __('Close menu') }}">
                            <x-heroicon-o-x-mark class="h-5 w-5" />
                        </x-button.icon>
                    </div>

                    @includeWhen($team !== null, 'teams::partials.team-sidebar-nav', ['team' => $team])
                </div>
            </aside>

            {{-- Main column --}}
            <div class="flex min-w-0 flex-1 flex-col">
                <header class="sticky top-0 z-30 flex h-16 flex-shrink-0 items-center gap-3 border-b border-base-300 bg-base-100/80 px-4 backdrop-blur md:px-8">
                    <x-button.icon class="md:hidden" @click="drawerOpen = !drawerOpen" title="{{ __('Open menu') }}">
                        <x-heroicon-o-bars-3 class="h-5 w-5" />
                    </x-button.icon>

                    @if ($team)
                        <x-teams::team-switcher :team="$team" />
                    @endif

                    <div class="flex-1"></div>

                    <x-header-menu />
                </header>

                <main class="flex-1 overflow-y-auto">
                    <div class="py-6">
                        <div class="ui-page flex flex-col gap-4">
                            @session('status')
                                <x-alert color="success">{{ $value }}</x-alert>
                            @endsession

                            {{ $slot }}
                        </div>
                    </div>
                </main>
            </div>
        </div>
    </div>

    @livewire('notifications')

    @filamentScripts
    @livewireScripts
</body>
</html>
