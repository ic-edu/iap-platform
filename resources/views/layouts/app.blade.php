<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="{{ Auth::user()?->getThemePreference() ?? session('theme_preference', 'dark') }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Early Theme Initialization to prevent flash of wrong theme -->
        <script>
            (function() {
                var preference = '{{ Auth::user()?->getThemePreference() ?? session('theme_preference', 'dark') }}';
                function resolveTheme(pref) {
                    if (pref === 'system') {
                        return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
                    }
                    return pref === 'light' ? 'light' : 'dark';
                }
                var activeTheme = resolveTheme(preference);
                var root = document.documentElement;
                root.setAttribute('data-theme', activeTheme);
                root.setAttribute('data-preference', preference);
                if (activeTheme === 'dark') {
                    root.classList.add('dark');
                    root.classList.remove('light');
                } else {
                    root.classList.add('light');
                    root.classList.remove('dark');
                }
            })();
        </script>

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased text-gray-900 dark:text-gray-100">
        <div class="min-h-screen bg-slate-50 dark:bg-slate-950">
            @include('layouts.navigation')

            <!-- Page Heading -->
            @isset($header)
                <header class="border-b border-slate-200 bg-white/80 backdrop-blur dark:border-slate-800 dark:bg-slate-950/80 lg:pl-72">
                    <div class="px-4 py-6 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <!-- Page Content -->
            <main class="lg:pl-72">
                {{ $slot }}
            </main>
        </div>
    </body>
</html>
