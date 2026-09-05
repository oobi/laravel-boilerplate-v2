<!DOCTYPE html>
@php
    $themeMode = request()->cookie('theme', 'auto');
@endphp
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="{{ $themeMode === 'dark' ? 'boilerplate-dark' : 'boilerplate' }}" data-theme-mode="{{ $themeMode }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? config('app.name', 'Laravel') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <x-theme-init-script />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-base-100 font-sans antialiased {{ auth()->check() && auth()->user()->isImpersonated() ? 'is-impersonating' : '' }}">
    <x-impersonation-banner />

    @include('layouts.partials.public-nav')

    <main class="ui-page py-10">
        {{ $slot }}
    </main>

    @filamentScripts
    @livewireScripts
</body>
</html>
