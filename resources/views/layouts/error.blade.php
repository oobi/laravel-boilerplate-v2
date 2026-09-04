<!DOCTYPE html>
@php
    $themeMode = request()->cookie('theme', 'auto');
@endphp
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="{{ $themeMode === 'dark' ? 'boilerplate-dark' : 'boilerplate' }}" data-theme-mode="{{ $themeMode }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Laravel') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <x-theme-init-script />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-base-100">
    <div class="mx-auto flex min-h-screen max-w-[760px] items-center px-6 py-12">
        <div class="ui-error-layout w-full">
            <div class="ui-error-copy flex flex-col gap-6">
                <x-application-logo size="h-12" class="self-start" />

                @yield('content')
            </div>

            {{-- illustration supplied per error code, dropped into public/images/errors/ --}}
            <div class="ui-error-art">
                @yield('illustration')
            </div>
        </div>
    </div>
</body>
</html>
