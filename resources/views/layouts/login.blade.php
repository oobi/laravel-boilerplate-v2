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
<body class="min-h-screen bg-base-200 flex flex-col items-center justify-center gap-6 p-4">
    <main class="flex w-full max-w-md flex-col items-center gap-6">
        <x-application-logo size="h-16" />

        <x-card bordered="false" class="w-full shadow-xl">
            @yield('content')
        </x-card>
    </main>
</body>
</html>
