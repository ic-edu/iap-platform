<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'iC.edu Assessment Platform') }} — Candidate Portal</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Theme & Visual Mode Initialization -->
    <script>
        (function() {
            var userTheme = '{{ Auth::user()?->getThemePreference() ?? session('theme_preference', 'dark') }}';
            function applyTheme(theme) {
                var root = document.documentElement;
                if (theme === 'light') {
                    root.classList.remove('dark');
                    root.classList.add('light');
                    root.setAttribute('data-theme', 'light');
                } else if (theme === 'system') {
                    var prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                    if (prefersDark) {
                        root.classList.add('dark');
                        root.classList.remove('light');
                        root.setAttribute('data-theme', 'dark');
                    } else {
                        root.classList.remove('dark');
                        root.classList.add('light');
                        root.setAttribute('data-theme', 'light');
                    }
                } else {
                    root.classList.add('dark');
                    root.classList.remove('light');
                    root.setAttribute('data-theme', 'dark');
                }
            }
            window.applyIapTheme = applyTheme;
            applyTheme(userTheme);

            if (userTheme === 'system') {
                window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function() {
                    applyTheme('system');
                });
            }
        })();
    </script>
</head>
<body class="font-sans antialiased bg-slate-950 text-slate-100 min-h-screen flex flex-col">
    <!-- Candidate Navigation Bar -->
    <nav class="bg-slate-900 border-b border-slate-800 sticky top-0 z-40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <div class="flex items-center gap-3">
                    <a href="{{ route('candidate.portal') }}" class="flex items-center gap-2">
                        <span class="w-8 h-8 rounded-lg bg-indigo-600 flex items-center justify-center font-bold text-white shadow-md shadow-indigo-500/30">iC</span>
                        <span class="font-bold text-white tracking-wide text-lg">iC.edu <span class="text-indigo-400 font-medium">Student</span></span>
                    </a>
                </div>

                <div class="flex items-center gap-6 text-sm font-medium">
                    <a href="{{ route('candidate.portal') }}" class="{{ request()->routeIs('candidate.portal') ? 'text-indigo-400 font-bold' : 'text-slate-300 hover:text-white' }} transition-colors">Portal Dashboard</a>
                    <a href="{{ route('candidate.available-tests') }}" class="{{ request()->routeIs('candidate.available-tests') ? 'text-indigo-400 font-bold' : 'text-slate-300 hover:text-white' }} transition-colors">Available Tests</a>
                    <a href="{{ route('candidate.my-attempts') }}" class="{{ request()->routeIs('candidate.my-attempts') ? 'text-indigo-400 font-bold' : 'text-slate-300 hover:text-white' }} transition-colors">My Attempts</a>
                    <a href="{{ route('candidate.my-certificates') }}" class="{{ request()->routeIs('candidate.my-certificates') ? 'text-indigo-400 font-bold' : 'text-slate-300 hover:text-white' }} transition-colors">🎓 My Certificates</a>
                    <a href="{{ route('settings.appearance') }}" class="{{ request()->routeIs('settings.appearance') ? 'text-indigo-400 font-bold' : 'text-slate-300 hover:text-white' }} transition-colors">🎨 Theme</a>
                </div>

                <div class="flex items-center gap-3">
                    <span class="text-xs text-slate-400 font-medium">{{ Auth::user()?->name ?? 'Candidate' }}</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="text-xs text-slate-400 hover:text-rose-400 transition-colors">Logout</button>
                    </form>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Candidate Content Area -->
    <main class="flex-1 max-w-7xl w-full mx-auto p-4 sm:p-6 lg:p-8">
        {{ $slot ?? '' }}
        @yield('content')
    </main>

    <!-- Footer -->
    <footer class="bg-slate-900 border-t border-slate-800/80 py-4 text-center text-xs text-slate-500">
        &copy; {{ date('Y') }} iC.edu Assessment Platform (IAP). Computer-Based Testing Delivery Engine.
    </footer>
</body>
</html>
