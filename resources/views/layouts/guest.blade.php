<!DOCTYPE html>
@php
    $themeMode = request()->cookie('theme', 'auto');
@endphp
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="{{ $themeMode === 'dark' ? 'boilerplate-dark' : 'boilerplate' }}" data-theme-mode="{{ $themeMode }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Laravel') }}</title>

    <x-theme-init-script />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-base-200 flex flex-col items-center justify-center gap-6 p-4">
    <x-application-logo size="h-16" />

    <div class="card w-full max-w-md bg-base-100 shadow-xl">
        <div class="card-body gap-4">
            @yield('content')
        </div>
    </div>
</body>
</html>
