<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" {{ \App\Support\Theme\ThemeMode::htmlAttributes() }}>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ \App\Support\PageTitle::for() }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <x-theme-init-script />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
{{-- Fixed top banners reserve their own space (layout.css), so the pt-4 drops when one shows. --}}
@php($impersonating = auth()->check() && auth()->user()->isImpersonated())
<body class="min-h-dvh bg-base-200 flex flex-col items-center justify-center gap-6 px-4 pb-4 {{ $impersonating ? 'is-impersonating' : '' }} {{ \App\Support\EnvironmentBanner::isShown() ? 'has-environment-banner' : '' }} {{ $impersonating || \App\Support\EnvironmentBanner::isShown() ? '' : 'pt-4' }}">
    <x-environment-banner />
    {{-- A signed-in page here (confirm password) must still let an impersonator leave. --}}
    <x-impersonation-banner />

    <main class="flex w-full max-w-md flex-col items-center gap-6">
        <x-application-logo size="h-16" />

        <x-card bordered="false" class="w-full shadow-xl">
            @yield('content')
        </x-card>
    </main>
</body>
</html>
