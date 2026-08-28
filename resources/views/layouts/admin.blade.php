<!DOCTYPE html>
@php
    $themeMode = request()->cookie('theme', 'auto');
@endphp
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="{{ $themeMode === 'dark' ? 'boilerplate-dark' : 'boilerplate' }}" data-theme-mode="{{ $themeMode }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? config('app.name', 'Laravel') }}</title>

    <x-theme-init-script />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @filamentStyles
    @livewireStyles
</head>
<body class="font-sans antialiased">
    <div class="ui-app">
        <div
            x-data="{
                sidebarPinned: JSON.parse(localStorage.getItem('ui.sidebar.pinned') ?? 'true'),
                sidebarDrawerOpen: false,
                togglePin() {
                    this.sidebarPinned = !this.sidebarPinned;
                    localStorage.setItem('ui.sidebar.pinned', this.sidebarPinned ? 'true' : 'false');
                    if (!this.sidebarPinned) this.sidebarDrawerOpen = false;
                },
            }"
            @resize.window="if (window.innerWidth < 768) sidebarDrawerOpen = false"
            class="ui-app-inner flex"
        >
            {{-- Drawer backdrop — shown when the drawer is open (mobile, or desktop while unpinned) --}}
            <div
                x-show="sidebarDrawerOpen"
                x-transition:enter="transition-opacity ease-linear duration-200"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition-opacity ease-linear duration-200"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                @click="sidebarDrawerOpen = false"
                class="fixed inset-0 z-40 bg-black/50"
                style="display: none;"
            ></div>

            {{-- Drawer sidebar — slides in from the left (mobile always, desktop when unpinned) --}}
            <aside
                x-show="sidebarDrawerOpen"
                @click.outside="sidebarDrawerOpen = false"
                x-transition:enter="transition ease-in-out duration-200 transform"
                x-transition:enter-start="-translate-x-full"
                x-transition:enter-end="translate-x-0"
                x-transition:leave="transition ease-in-out duration-200 transform"
                x-transition:leave-start="translate-x-0"
                x-transition:leave-end="-translate-x-full"
                class="fixed inset-y-0 left-0 z-50 w-64"
                style="display: none;"
                x-cloak
            >
                <div class="flex h-full flex-col border-r border-base-300 bg-base-100">
                    <div class="flex h-16 flex-shrink-0 items-center justify-between border-b border-base-300 px-4">
                        <x-application-logo class="min-w-0 flex-1" />
                        <div class="flex flex-shrink-0 items-center gap-1">
                            {{-- Pin button (desktop only) — pins the sidebar as a persistent column --}}
                            <button
                                type="button"
                                @click="togglePin(); sidebarDrawerOpen = false"
                                class="btn btn-ghost btn-square btn-sm hidden md:inline-flex"
                                title="{{ __('Pin sidebar') }}"
                            >
                                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" fill="currentColor"><path d="M81.3 47.1L64.3 30.1L30.4 64L47.4 81L559.4 593L576.4 610L610.3 576.1L593.3 559.1L450.2 416L512.2 416C512.2 399.8 510.2 383.7 506.4 368C495.7 323.6 470.7 282.7 436.8 253.4L427.4 112L480.2 112L480.2 64L166 64L213 111L208.8 174.6L81.3 47.1zM253.9 219.7L261.1 112L379.3 112L388.9 256.6L390.2 276.6L405.4 289.7C429 310.2 447 337.9 456.5 368L402.1 368L253.8 219.7zM314.3 416L266.3 368L183.7 368C189.9 348.5 199.6 330 212.1 313.8L178 279.7C157.1 305.2 141.7 335.7 133.9 368C130.1 383.7 128.1 399.8 128.1 416L314.3 416zM296.1 584L296.1 608L344.1 608L344.1 464L296.1 464L296.1 584z"/></svg>
                            </button>
                            <button type="button" class="btn btn-ghost btn-square btn-sm" @click="sidebarDrawerOpen = false" aria-label="{{ __('Close menu') }}">
                                <x-heroicon-o-x-mark class="h-5 w-5" />
                            </button>
                        </div>
                    </div>

                    @include('layouts.partials.admin-sidebar-nav')
                </div>
            </aside>

            {{-- Pinned sidebar — persistent column for desktop while pinned --}}
            <aside class="hidden flex-shrink-0 w-64" :class="sidebarPinned ? 'md:flex' : ''">
                <div class="flex h-full w-64 flex-col border-r border-base-300 bg-base-100">
                    <div class="flex h-16 flex-shrink-0 items-center justify-between border-b border-base-300 px-4">
                        <x-application-logo class="min-w-0 flex-1" />
                        {{-- Unpin button — switches the sidebar to drawer mode --}}
                        <button
                            type="button"
                            @click="togglePin()"
                            class="btn btn-ghost btn-square btn-sm text-primary flex-shrink-0"
                            title="{{ __('Unpin sidebar') }}"
                        >
                            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" fill="currentColor"><path d="M184 64C170.7 64 160 74.7 160 88C160 101.3 170.7 112 184 112L214.2 112L204 250.2C186.7 262.6 169.8 279.3 156.5 298.2C140.5 320.7 128 348.5 128 377.7C128 398.8 145.2 416 166.3 416L473.6 416C494.8 416 511.9 398.8 511.9 377.7C511.9 348.5 499.4 320.7 483.4 298.2C470 279.3 453.2 262.7 435.9 250.2L425.8 112L456 112C469.3 112 480 101.3 480 88C480 74.7 469.3 64 456 64L184 64zM377.7 112L388.2 253.8L389.9 276.1L408.1 289.2C424.2 300.8 437.4 315.5 448.5 332C455.4 342.8 461.5 355.3 463.2 368.1L176.8 368C179.3 352.7 186.7 338.5 195.6 325.9C205.6 311.8 218.5 298.8 232 289.1L250.2 276L251.9 253.7L262.4 111.9L377.8 111.9zM296 584C296 597.3 306.7 608 320 608C333.3 608 344 597.3 344 584L344 464L296 464L296 584z"/></svg>
                        </button>
                    </div>

                    @include('layouts.partials.admin-sidebar-nav')
                </div>
            </aside>

            {{-- Main column --}}
            <div class="flex min-w-0 flex-1 flex-col">
                <header class="sticky top-0 z-30 flex h-16 flex-shrink-0 items-center gap-3 border-b border-base-300 bg-base-100/80 px-4 backdrop-blur">
                    {{-- Logo — visible on desktop only while the sidebar is unpinned --}}
                    <x-application-logo
                        variant="icon"
                        href="{{ route('dashboard') }}"
                        x-show="!sidebarPinned"
                        x-cloak
                        class="hidden flex-shrink-0 items-center md:flex"
                    />

                    {{-- Mobile menu button --}}
                    <button type="button" class="btn btn-ghost btn-square btn-sm md:hidden" @click="sidebarDrawerOpen = !sidebarDrawerOpen" title="{{ __('Open menu') }}">
                        <x-heroicon-o-bars-3 class="h-5 w-5" />
                    </button>
                    {{-- Desktop menu button — visible when the sidebar is unpinned --}}
                    <button type="button" x-show="!sidebarPinned" x-cloak class="btn btn-ghost btn-square btn-sm hidden md:inline-flex" @click="sidebarDrawerOpen = !sidebarDrawerOpen" title="{{ __('Open menu') }}">
                        <x-heroicon-o-bars-3 class="h-5 w-5" />
                    </button>

                    <div class="min-w-0 flex-1 text-sm text-base-content/60">
                        <span>{{ __('admin.breadcrumb_root') }}</span>
                        <span class="mx-2">/</span>
                        <span>@yield('page-title', __('Dashboard'))</span>
                    </div>

                    <x-header-menu />
                </header>

                <main class="flex-1 overflow-y-auto">
                    <div class="py-6">
                        <div class="ui-page flex flex-col gap-4">
                            @session('status')
                                <div class="alert alert-success">{{ $value }}</div>
                            @endsession

                            {{ $slot }}
                        </div>
                    </div>
                </main>
            </div>
        </div>
    </div>

    @filamentScripts
    @livewireScripts
</body>
</html>
